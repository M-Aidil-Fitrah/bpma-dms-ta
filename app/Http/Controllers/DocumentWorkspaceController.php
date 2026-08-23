<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\DocumentListData;
use App\Http\Requests\DocumentIndexRequest;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentStar;
use App\Models\User;
use App\Services\DocumentListingService;
use App\Services\DocumentWorkspaceService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            queryDasarDokumen: Document::query()
                ->active()
                ->notTrashed()
                ->where('uploaded_by', $user->id)
                ->whereHas('placements', fn ($query) => $query->where('owner_id', $user->id)->where('folder_id', $folder->id)),
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

        return Inertia::render('Workspace/Index', [
            'title' => $title,
            'folder' => $folder === null ? null : ['id' => $folder->id, 'name' => $folder->name, 'parent_id' => $folder->parent_id],
            'breadcrumbs' => $this->breadcrumbs($folder),
            'folders' => $folders->map(fn (DocumentFolder $item): array => ['id' => $item->id, 'name' => $item->name])->all(),
            'folder_options' => DocumentFolder::query()
                ->where('owner_id', $userId)
                ->notTrashed()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (DocumentFolder $item): array => ['id' => $item->id, 'name' => $item->name])
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

    /** @return list<array{label: string, href: string}> */
    private function breadcrumbs(?DocumentFolder $folder): array
    {
        $ancestors = [];

        while ($folder !== null) {
            $ancestors[] = ['label' => $folder->name, 'href' => route('folders.show', $folder)];
            $folder = $folder->parent;
        }

        return [
            ['label' => 'Dokumen Saya', 'href' => route('documents.mine')],
            ...array_reverse($ancestors),
        ];
    }
}
