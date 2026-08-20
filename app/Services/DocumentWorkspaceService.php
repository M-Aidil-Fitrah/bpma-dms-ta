<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentPlacement;
use App\Models\DocumentRecent;
use App\Models\DocumentStar;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DocumentWorkspaceService
{
    private const RETENSI_HARI = 30;

    private const KEDALAMAN_MAKSIMAL = 5;

    public function createFolder(User $owner, ?DocumentFolder $parent, string $name): DocumentFolder
    {
        if ($parent !== null) {
            $this->pastikanFolderAktifMilik($parent, $owner);
            $this->pastikanKedalaman($parent);
        }

        $name = $this->namaBersih($name);
        $this->pastikanNamaTersedia($owner, $parent?->id, $name);

        return DocumentFolder::create([
            'owner_id' => $owner->id,
            'parent_id' => $parent?->id,
            'name' => $name,
            'name_normalized' => $this->namaNormal($name),
        ]);
    }

    public function renameFolder(DocumentFolder $folder, User $owner, string $name): void
    {
        $this->pastikanFolderAktifMilik($folder, $owner);
        $name = $this->namaBersih($name);
        $this->pastikanNamaTersedia($owner, $folder->parent_id, $name, $folder->id);
        $folder->update(['name' => $name, 'name_normalized' => $this->namaNormal($name)]);
    }

    public function placeDocument(Document $document, DocumentFolder $folder, User $owner): void
    {
        $this->pastikanFolderAktifMilik($folder, $owner);

        if ($document->uploaded_by !== $owner->id) {
            throw ValidationException::withMessages([
                'document' => 'Hanya dokumen yang Anda unggah yang dapat dimasukkan ke folder Dokumen Saya.',
            ]);
        }

        DocumentPlacement::query()->updateOrCreate(
            ['owner_id' => $owner->id, 'document_id' => $document->id],
            ['folder_id' => $folder->id],
        );
    }

    public function moveToRoot(Document $document, User $owner): void
    {
        if ($document->uploaded_by !== $owner->id) {
            throw ValidationException::withMessages([
                'document' => 'Hanya dokumen yang Anda unggah yang dapat dipindahkan dari folder Dokumen Saya.',
            ]);
        }

        DocumentPlacement::query()
            ->where('owner_id', $owner->id)
            ->where('document_id', $document->id)
            ->delete();
    }

    public function star(Document $document, User $user): void
    {
        DocumentStar::query()->firstOrCreate(['user_id' => $user->id, 'document_id' => $document->id]);
    }

    public function unstar(Document $document, User $user): void
    {
        DocumentStar::query()->where('user_id', $user->id)->where('document_id', $document->id)->delete();
    }

    public function recordRecent(Document $document, User $user): void
    {
        DocumentRecent::query()->updateOrCreate(
            ['user_id' => $user->id, 'document_id' => $document->id],
            ['last_opened_at' => now()],
        );
    }

    public function trashDocument(Document $document, User $user): string
    {
        $rootId = $document->version_root_id ?? $document->id;
        $token = (string) Str::uuid();

        DB::transaction(function () use ($rootId, $token, $user): void {
            Document::query()->where('version_root_id', $rootId)->update([
                'trashed_at' => now(),
                'trashed_by' => $user->id,
                'purge_after' => now()->addDays(self::RETENSI_HARI),
                'trash_token' => $token,
            ]);
        });

        return $token;
    }

    public function restoreDocument(Document $document): void
    {
        $rootId = $document->version_root_id ?? $document->id;
        $token = $document->trash_token;

        Document::query()
            ->where('version_root_id', $rootId)
            ->when($token !== null, fn ($query) => $query->where('trash_token', $token))
            ->update(['trashed_at' => null, 'trashed_by' => null, 'purge_after' => null, 'trash_token' => null]);
    }

    private function pastikanFolderAktifMilik(DocumentFolder $folder, User $owner): void
    {
        if ($folder->owner_id !== $owner->id || $folder->trashed_at !== null) {
            throw ValidationException::withMessages(['folder' => 'Folder tidak tersedia.']);
        }
    }

    private function pastikanKedalaman(DocumentFolder $parent): void
    {
        $kedalaman = 1;
        $node = $parent;
        while ($node->parent_id !== null) {
            $kedalaman++;
            $node = $node->parent()->firstOrFail();
        }

        if ($kedalaman >= self::KEDALAMAN_MAKSIMAL) {
            throw ValidationException::withMessages(['parent_id' => 'Folder hanya dapat memiliki lima tingkat.']);
        }
    }

    private function pastikanNamaTersedia(User $owner, ?int $parentId, string $name, ?int $exceptId = null): void
    {
        $ada = DocumentFolder::query()
            ->ownedBy($owner)
            ->notTrashed()
            ->where('parent_id', $parentId)
            ->where('name_normalized', $this->namaNormal($name))
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($ada) {
            throw ValidationException::withMessages(['name' => 'Nama folder sudah dipakai pada lokasi ini.']);
        }
    }

    private function namaBersih(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    }

    private function namaNormal(string $name): string
    {
        return mb_strtolower($name);
    }
}
