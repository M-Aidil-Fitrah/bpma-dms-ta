<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\DocumentAccessChanges;
use App\Data\DocumentListData;
use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Http\Requests\DocumentIndexRequest;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentStar;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\DocumentListingService;
use App\Services\DocumentWorkspaceService;
use App\Services\FolderAccessWriter;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class DocumentWorkspaceController extends Controller
{
    public function mine(DocumentIndexRequest $request, DocumentListingService $listing): Response
    {
        $user = $request->user();

        return $this->renderWorkspace(
            request: $request,
            listing: $listing,
            title: 'Dokumen Saya',
            folder: null,
            folders: DocumentFolder::query()->ownedBy($user)->notTrashed()->whereNull('parent_id')->orderBy('name')->get(),
            queryDasarDokumen: Document::query()
                ->active()
                ->notTrashed()
                ->where('uploaded_by', $user->id)
                ->whereDoesntHave('placements', fn ($query) => $query
                    ->where('owner_id', $user->id)
                    ->whereHas('folder', fn ($folderQuery) => $folderQuery->notTrashed())),
            userId: $user->id,
        );
    }

    public function folder(DocumentIndexRequest $request, DocumentFolder $folder, DocumentListingService $listing): Response
    {
        $this->authorize('view', $folder);
        abort_if($folder->trashed_at !== null, 404);
        $user = $request->user();

        return $this->renderWorkspace(
            request: $request,
            listing: $listing,
            title: $folder->name,
            folder: $folder,
            folders: $folder->children()->notTrashed()->orderBy('name')->get(),
            // Dokumen di dalam folder milik PEMILIK folder, bukan milik yang
            // sedang melihat: penerima share membuka folder orang lain dan
            // harus melihat isinya, bukan daftar kosong.
            queryDasarDokumen: Document::query()
                ->active()
                ->notTrashed()
                ->where('uploaded_by', $folder->owner_id)
                ->whereHas('placements', fn ($query) => $query->where('owner_id', $folder->owner_id)->where('folder_id', $folder->id)),
            userId: $user->id,
        );
    }

    public function starred(DocumentIndexRequest $request, DocumentListingService $listing): Response
    {
        $user = $request->user();

        $dokumen = $listing->paginasi(
            Document::query()
                ->visibleTo($user)
                ->active()
                ->whereHas('stars', fn ($query) => $query->where('user_id', $user->id)),
            $request,
            $user,
            // Sudah pasti berbintang oleh definisi daftar ini — tidak perlu
            // query tambahan untuk memastikannya per baris.
            fn (Document $document): DocumentListData => DocumentListData::untukWorkspace($document, $user, distarai: true),
        );

        return Inertia::render('Workspace/Collection', ['title' => 'Berbintang', 'dokumen' => $dokumen, 'filter' => $request->filterAktif()]);
    }

    public function recent(DocumentIndexRequest $request, DocumentListingService $listing): Response
    {
        $user = $request->user();

        $dokumen = $listing->paginasi(
            Document::query()
                ->visibleTo($user)
                ->active()
                ->whereHas('recents', fn ($query) => $query->where('user_id', $user->id)),
            $request,
            $user,
            $this->pemetaanDenganBintang($user),
        );

        return Inertia::render('Workspace/Collection', ['title' => 'Terbaru Dibuka', 'dokumen' => $dokumen, 'filter' => $request->filterAktif()]);
    }

    public function trash(DocumentIndexRequest $request, DocumentListingService $listing): Response
    {
        $user = $request->user();

        $dokumen = $listing->paginasi(
            Document::query()
                ->whereNotNull('trashed_at')
                ->when(! $user->isSuperadmin(), fn ($query) => $query->where('uploaded_by', $user->id)),
            $request,
            $user,
            fn (Document $document): DocumentListData => DocumentListData::untukWorkspace($document, $user, distarai: false),
        );
        $folders = DocumentFolder::query()->ownedBy($user)->whereNotNull('trashed_at')->latest('trashed_at')->get();

        return Inertia::render('Workspace/Trash', [
            'dokumen' => $dokumen,
            'filter' => $request->filterAktif(),
            'folders' => $folders->map(fn (DocumentFolder $folder): array => [
                'id' => $folder->id,
                'name' => $folder->name,
                'purge_after' => $folder->purge_after?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function storeFolder(Request $request, DocumentWorkspaceService $workspace): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'parent_id' => ['nullable', 'integer', 'exists:document_folders,id']]);
        $parent = isset($data['parent_id']) ? DocumentFolder::query()->findOrFail($data['parent_id']) : null;
        $workspace->createFolder($request->user(), $parent, $data['name']);

        return back()->with('success', 'Folder berhasil dibuat.');
    }

    public function updateFolder(Request $request, DocumentFolder $folder, DocumentWorkspaceService $workspace): RedirectResponse
    {
        $this->authorize('update', $folder);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $workspace->renameFolder($folder, $request->user(), $data['name']);

        return back()->with('success', 'Nama folder berhasil diubah.');
    }

    public function trashFolder(Request $request, DocumentFolder $folder, DocumentWorkspaceService $workspace): RedirectResponse
    {
        $this->authorize('delete', $folder);
        $workspace->trashFolder($folder, $request->user());

        return redirect()->route('documents.mine')->with('success', 'Folder dipindahkan ke Sampah.');
    }

    public function restoreFolder(Request $request, DocumentFolder $folder, DocumentWorkspaceService $workspace): RedirectResponse
    {
        $this->authorize('view', $folder);
        $workspace->restoreFolder($folder, $request->user());

        return back()->with('success', 'Folder berhasil dipulihkan.');
    }

    /**
     * Menyimpan daftar unit dan orang yang boleh melihat sebuah folder.
     *
     * Dijaga ability `share` — hanya pemilik folder, penerima share tidak
     * boleh membagikan ulang (Fase 1).
     */
    public function share(Request $request, DocumentFolder $folder, FolderAccessWriter $akses, ActivityLogService $aktivitas): RedirectResponse
    {
        $this->authorize('share', $folder);

        $data = $request->validate([
            'unit_ids' => ['array'],
            'unit_ids.*' => ['integer', Rule::exists('units', 'id')->where('is_active', true)],
            'shared_user_ids' => ['array'],
            'shared_user_ids.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)],
        ]);

        $perubahan = $akses->sinkron(
            $folder,
            array_map(intval(...), $data['unit_ids'] ?? []),
            array_map(intval(...), $data['shared_user_ids'] ?? []),
            $request->user(),
        );

        $this->catatPerubahanAksesFolder($aktivitas, $folder, $request->user(), $perubahan);

        return back()->with('success', 'Akses folder berhasil diperbarui.');
    }

    /**
     * "Dibagikan ke saya" — hanya folder yang aksesnya diberikan LANGSUNG ke
     * pengguna ini (atau ke unitnya). Subfolder yang ikut terlihat lewat
     * warisan sengaja tidak didaftar di sini: tempatnya di dalam folder
     * induknya, bukan sebagai entri terpisah di akar daftar.
     */
    public function shared(Request $request): Response
    {
        $user = $request->user();

        $folders = DocumentFolder::query()
            ->notTrashed()
            ->with('owner:id,name')
            ->where(function (Builder $q) use ($user): void {
                $q->whereHas('sharedUsers', fn ($sq) => $sq->where('users.id', $user->id));

                if ($user->unit_id !== null) {
                    $q->orWhereHas('targetUnits', fn ($sq) => $sq->where('units.id', $user->unit_id));
                }
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('Workspace/Shared', [
            'folders' => $folders->map(fn (DocumentFolder $folder): array => [
                'id' => $folder->id,
                'name' => $folder->name,
                'owner_name' => $folder->owner->name,
            ])->all(),
        ]);
    }

    public function place(Request $request, Document $document, DocumentWorkspaceService $workspace): RedirectResponse
    {
        $this->authorize('view', $document);
        $data = $request->validate(['folder_id' => ['required', 'integer', 'exists:document_folders,id']]);
        $workspace->placeDocument($document, DocumentFolder::query()->findOrFail($data['folder_id']), $request->user());

        return back()->with('success', 'Dokumen dipindahkan ke folder.');
    }

    public function moveToRoot(Request $request, Document $document, DocumentWorkspaceService $workspace): RedirectResponse
    {
        $this->authorize('view', $document);
        $workspace->moveToRoot($document, $request->user());

        return back()->with('success', 'Dokumen dipindahkan ke akar Dokumen Saya.');
    }

    public function star(Request $request, Document $document, DocumentWorkspaceService $workspace): RedirectResponse
    {
        $this->authorize('view', $document);
        $workspace->star($document, $request->user());

        return back()->with('success', 'Dokumen ditandai berbintang.');
    }

    public function unstar(Request $request, Document $document, DocumentWorkspaceService $workspace): RedirectResponse
    {
        $this->authorize('view', $document);
        $workspace->unstar($document, $request->user());

        return back()->with('success', 'Tanda bintang dihapus.');
    }

    /**
     * @param  Collection<int, DocumentFolder>  $folders
     * @param  Builder<Document>  $queryDasarDokumen
     */
    private function renderWorkspace(
        DocumentIndexRequest $request,
        DocumentListingService $listing,
        string $title,
        ?DocumentFolder $folder,
        Collection $folders,
        Builder $queryDasarDokumen,
        int $userId,
    ): Response {
        $user = $request->user();
        // Dimuat sekaligus untuk seluruh folder di halaman ini: dialog share
        // menampilkan ringkasan akses tiap kartu folder, dan memuatnya per
        // kartu berarti dua kueri tambahan per baris.
        $folders->load(['targetUnits:id,nama', 'sharedUsers:id,name,jabatan_id,unit_id', 'sharedUsers.jabatan:id,nama', 'sharedUsers.unit:id,nama']);

        return Inertia::render('Workspace/Index', [
            'title' => $title,
            'folder' => $folder === null ? null : ['id' => $folder->id, 'name' => $folder->name, 'parent_id' => $folder->parent_id, 'owner_id' => $folder->owner_id],
            'breadcrumbs' => $this->breadcrumbs($folder, $user),
            'folders' => $folders->map(fn (DocumentFolder $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'unit_ids' => $item->targetUnits->pluck('id')->all(),
                'shared_users' => $item->sharedUsers->map(fn (User $u): array => [
                    'id' => $u->id,
                    'nama' => $u->name,
                    'jabatan' => $u->jabatan?->nama,
                    'unit' => $u->unit?->nama,
                ])->all(),
            ])->all(),
            // Tetap folder milik VIEWER, bukan milik pemilik folder yang
            // sedang dibuka: daftar ini adalah tujuan "pindahkan dokumen ke
            // folder", dan penerima share hanya boleh memindahkan ke
            // foldernya sendiri.
            'folder_options' => DocumentFolder::query()
                ->where('owner_id', $userId)
                ->notTrashed()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (DocumentFolder $item): array => ['id' => $item->id, 'name' => $item->name])
                ->all(),
            // Dipakai `FolderSharePicker` lewat `WorkspaceFolderCard` —
            // mengikuti pola `AccessMechanismPicker`, yang juga menerima
            // daftar unit sebagai prop halaman, bukan dari shared Inertia
            // props.
            'unit_options' => Unit::query()->active()->orderBy('nama')->get(['id', 'nama', 'parent_id'])
                ->map(fn (Unit $unit): array => ['id' => $unit->id, 'nama' => $unit->nama, 'parent_id' => $unit->parent_id])
                ->all(),
            'dokumen' => $listing->paginasi($queryDasarDokumen, $request, $user, $this->pemetaanDenganBintang($user)),
            'filter' => $request->filterAktif(),
        ]);
    }

    /** @return Closure(Document): DocumentListData */
    private function pemetaanDenganBintang(User $user): Closure
    {
        // Dihitung per halaman, bukan per baris (N+1 pada halaman dokumen
        // milik sendiri yang jarang lebih dari selusin baris cukup murah
        // sebagai satu kueri terindeks per baris — bandingkan dengan
        // menyimpan seluruh `document_id` berbintang milik pengguna di
        // memori, yang bisa berjumlah ratusan pada akun lama).
        return fn (Document $document): DocumentListData => DocumentListData::untukWorkspace(
            $document,
            $user,
            distarai: DocumentStar::query()->where('user_id', $user->id)->where('document_id', $document->id)->exists(),
        );
    }

    /**
     * Satu baris aktivitas per target yang berubah, bukan satu baris per
     * penyimpanan — sama seperti `DocumentController::catatPerubahanAkses()`.
     * Pertanyaan yang dijawab jejak ini selalu "akses siapa yang berubah",
     * dan itu hanya terjawab bila targetnya tercatat satu per satu.
     */
    private function catatPerubahanAksesFolder(
        ActivityLogService $aktivitas,
        DocumentFolder $folder,
        User $pelaku,
        DocumentAccessChanges $perubahan,
    ): void {
        foreach ($perubahan->unitDitambahkan as $unit) {
            $aktivitas->record(
                ActivityLogName::FolderUnit,
                AuditEvent::AccessGranted,
                "Akses folder untuk \"{$unit['nama']}\" ditambahkan.",
                $folder,
                $pelaku,
                ['target' => $unit],
            );
        }

        foreach ($perubahan->unitDicabut as $unit) {
            $aktivitas->record(
                ActivityLogName::FolderUnit,
                AuditEvent::AccessRevoked,
                "Akses folder untuk \"{$unit['nama']}\" dicabut.",
                $folder,
                $pelaku,
                ['target' => $unit],
            );
        }

        foreach ($perubahan->penggunaDitambahkan as $pengguna) {
            $aktivitas->record(
                ActivityLogName::FolderShare,
                AuditEvent::AccessGranted,
                "Akses folder untuk \"{$pengguna['nama']}\" ditambahkan.",
                $folder,
                $pelaku,
                ['target' => $pengguna],
            );
        }

        foreach ($perubahan->penggunaDicabut as $pengguna) {
            $aktivitas->record(
                ActivityLogName::FolderShare,
                AuditEvent::AccessRevoked,
                "Akses folder untuk \"{$pengguna['nama']}\" dicabut.",
                $folder,
                $pelaku,
                ['target' => $pengguna],
            );
        }
    }

    /** @return list<array{label: string, href: string}> */
    private function breadcrumbs(?DocumentFolder $folder, User $viewer): array
    {
        if ($folder === null) {
            return [['label' => 'Dokumen Saya', 'href' => route('documents.mine')]];
        }

        if ($folder->owner_id === $viewer->id) {
            $ancestors = [];
            $node = $folder;

            while ($node !== null) {
                $ancestors[] = ['label' => $node->name, 'href' => route('folders.show', $node)];
                $node = $node->parent;
            }

            return [['label' => 'Dokumen Saya', 'href' => route('documents.mine')], ...array_reverse($ancestors)];
        }

        // Penerima share: berhenti di ancestor terdekat (termasuk folder ini
        // sendiri) yang benar-benar punya baris grant langsung — tidak boleh
        // menaiki rantai `parent` sampai ke folder pemilik yang tidak
        // diberi akses kepadanya.
        $chain = [];
        $node = $folder;

        while ($node !== null) {
            $chain[] = $node;

            if ($node->sharedUsers()->where('users.id', $viewer->id)->exists()
                || ($viewer->unit_id !== null && $node->targetUnits()->where('units.id', $viewer->unit_id)->exists())) {
                break;
            }

            $node = $node->parent;
        }

        $ancestors = array_map(
            fn (DocumentFolder $item): array => ['label' => $item->name, 'href' => route('folders.show', $item)],
            array_reverse($chain),
        );

        return [['label' => 'Dibagikan ke saya', 'href' => route('documents.shared')], ...$ancestors];
    }
}
