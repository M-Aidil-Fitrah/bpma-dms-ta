<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentRecent;
use App\Models\DocumentStar;
use App\Services\DocumentWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class DocumentWorkspaceController extends Controller
{
    public function mine(Request $request): Response
    {
        $user = $request->user();

        return $this->renderWorkspace(
            title: 'Dokumen Saya',
            folder: null,
            folders: DocumentFolder::query()->ownedBy($user)->notTrashed()->whereNull('parent_id')->orderBy('name')->get(),
            documents: Document::query()
                ->active()
                ->notTrashed()
                ->where('uploaded_by', $user->id)
                ->whereDoesntHave('placements', fn ($query) => $query
                    ->where('owner_id', $user->id)
                    ->whereHas('folder', fn ($folderQuery) => $folderQuery->notTrashed()))
                ->select(Document::KOLOM_DAFTAR)
                ->latest('id')
                ->get(),
            userId: $user->id,
        );
    }

    public function folder(Request $request, DocumentFolder $folder): Response
    {
        $this->authorize('view', $folder);
        abort_if($folder->trashed_at !== null, 404);
        $user = $request->user();

        return $this->renderWorkspace(
            title: $folder->name,
            folder: $folder,
            folders: $folder->children()->notTrashed()->orderBy('name')->get(),
            documents: Document::query()
                ->active()
                ->notTrashed()
                ->where('uploaded_by', $user->id)
                ->whereHas('placements', fn ($query) => $query->where('owner_id', $user->id)->where('folder_id', $folder->id))
                ->select(Document::KOLOM_DAFTAR)
                ->latest('id')
                ->get(),
            userId: $user->id,
        );
    }

    public function starred(Request $request): Response
    {
        $user = $request->user();

        return $this->renderCollection(
            'Berbintang',
            Document::query()
                ->visibleTo($user)
                ->active()
                ->whereHas('stars', fn ($query) => $query->where('user_id', $user->id))
                ->select(Document::KOLOM_DAFTAR)
                ->orderByDesc(DocumentStar::query()->select('created_at')->whereColumn('document_id', 'documents.id')->where('user_id', $user->id))
                ->get(),
            $user->id,
        );
    }

    public function recent(Request $request): Response
    {
        $user = $request->user();

        return $this->renderCollection(
            'Terbaru Dibuka',
            Document::query()
                ->visibleTo($user)
                ->active()
                ->whereHas('recents', fn ($query) => $query->where('user_id', $user->id))
                ->select(Document::KOLOM_DAFTAR)
                ->orderByDesc(DocumentRecent::query()->select('last_opened_at')->whereColumn('document_id', 'documents.id')->where('user_id', $user->id))
                ->get(),
            $user->id,
        );
    }

    public function trash(Request $request): Response
    {
        $user = $request->user();
        $documents = Document::query()
            ->whereNotNull('trashed_at')
            ->when(! $user->isSuperadmin(), fn ($query) => $query->where('uploaded_by', $user->id))
            ->select([...Document::KOLOM_DAFTAR, 'trashed_at', 'purge_after'])
            ->latest('trashed_at')
            ->get();
        $folders = DocumentFolder::query()->ownedBy($user)->whereNotNull('trashed_at')->latest('trashed_at')->get();

        return Inertia::render('Workspace/Trash', [
            'documents' => $this->documents($documents, $user->id),
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
     * @param  Collection<int, Document>  $documents
     */
    private function renderWorkspace(string $title, ?DocumentFolder $folder, Collection $folders, Collection $documents, int $userId): Response
    {
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
            'documents' => $this->documents($documents, $userId),
        ]);
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

    /** @param Collection<int, Document> $documents */
    private function renderCollection(string $title, Collection $documents, int $userId): Response
    {
        return Inertia::render('Workspace/Collection', ['title' => $title, 'documents' => $this->documents($documents, $userId)]);
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return list<array{id: int, judul: string, nomor: string, tipe: string, thumbnail_tersedia: bool, is_private: bool, starred: bool, trashed_at: string|null, purge_after: string|null}>
     */
    private function documents(Collection $documents, int $userId): array
    {
        $starredIds = DocumentStar::query()->where('user_id', $userId)->whereIn('document_id', $documents->pluck('id'))->pluck('document_id')->all();

        return $documents->map(fn (Document $document): array => [
            'id' => $document->id,
            'judul' => $document->judul,
            'nomor' => $document->nomor,
            'tipe' => $document->file_mime_type,
            'thumbnail_tersedia' => $document->thumbnail_path !== null,
            'is_private' => $document->is_private,
            'starred' => in_array($document->id, $starredIds, true),
            'trashed_at' => $document->trashed_at?->toIso8601String(),
            'purge_after' => $document->purge_after?->toIso8601String(),
        ])->all();
    }
}
