<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
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

    public function __construct(private readonly ActivityLogService $aktivitas) {}

    public function createFolder(User $pelaku, ?DocumentFolder $parent, string $name): DocumentFolder
    {
        if ($parent !== null) {
            $this->pastikanFolderAktifBolehDiedit($parent, $pelaku);
            $this->pastikanKedalaman($parent);
        }

        // Root milik pelaku sendiri; subfolder mewarisi pohon induknya
        // (Editor tidak pernah membuat folder ROOT di pohon orang lain).
        $ownerId = $parent?->owner_id ?? $pelaku->id;

        $name = $this->namaBersih($name);
        $this->pastikanNamaTersedia($ownerId, $parent?->id, $name);

        $folder = DocumentFolder::create([
            'owner_id' => $ownerId,
            'parent_id' => $parent?->id,
            'name' => $name,
            'name_normalized' => $this->namaNormal($name),
        ]);

        $this->aktivitas->record(
            ActivityLogName::DocumentWorkspace,
            AuditEvent::FolderCreated,
            "Folder \"{$folder->name}\" dibuat.",
            $folder,
            $pelaku,
            ['folder_induk' => $parent?->name ?? 'Dokumen Saya'],
        );

        return $folder;
    }

    public function renameFolder(DocumentFolder $folder, User $owner, string $name): void
    {
        $this->pastikanFolderAktifMilik($folder, $owner);
        $name = $this->namaBersih($name);
        $this->pastikanNamaTersedia($folder->owner_id, $folder->parent_id, $name, $folder->id);
        $namaSebelumnya = $folder->name;
        $folder->update(['name' => $name, 'name_normalized' => $this->namaNormal($name)]);

        $this->aktivitas->record(
            ActivityLogName::DocumentWorkspace,
            AuditEvent::FolderRenamed,
            "Nama folder diubah dari \"{$namaSebelumnya}\" menjadi \"{$folder->name}\".",
            $folder,
            $owner,
            [],
            ['nama' => $namaSebelumnya],
            ['nama' => $folder->name],
        );
    }

    public function placeDocument(Document $document, DocumentFolder $folder, User $owner): void
    {
        $this->pastikanFolderAktifMilik($folder, $owner);

        if ($document->uploaded_by !== $owner->id) {
            throw ValidationException::withMessages([
                'document' => 'Hanya dokumen yang Anda unggah yang dapat dimasukkan ke folder Dokumen Saya.',
            ]);
        }

        $placement = DocumentPlacement::query()
            ->with('folder:id,name')
            ->where('owner_id', $owner->id)
            ->where('document_id', $document->id)
            ->first();
        $asal = $placement?->folder?->name ?? 'Dokumen Saya (tanpa folder)';

        if ($placement?->folder_id === $folder->id) {
            return;
        }

        DocumentPlacement::query()->updateOrCreate(
            ['owner_id' => $owner->id, 'document_id' => $document->id],
            ['folder_id' => $folder->id],
        );

        $this->catatPerpindahanDokumen($document, $owner, $asal, $folder->name);
    }

    public function moveToRoot(Document $document, User $owner): void
    {
        if ($document->uploaded_by !== $owner->id) {
            throw ValidationException::withMessages([
                'document' => 'Hanya dokumen yang Anda unggah yang dapat dipindahkan dari folder Dokumen Saya.',
            ]);
        }

        $placement = DocumentPlacement::query()
            ->with('folder:id,name')
            ->where('owner_id', $owner->id)
            ->where('document_id', $document->id)
            ->first();

        if ($placement === null) {
            return;
        }

        $asal = $placement->folder?->name ?? 'Folder Dokumen Saya';
        $placement->delete();
        $this->catatPerpindahanDokumen($document, $owner, $asal, 'Dokumen Saya (tanpa folder)');
    }

    public function star(Document $document, User $user): void
    {
        $star = DocumentStar::query()->firstOrCreate(['user_id' => $user->id, 'document_id' => $document->id]);

        if (! $star->wasRecentlyCreated) {
            return;
        }

        $this->aktivitas->record(
            ActivityLogName::DocumentWorkspace,
            AuditEvent::DocumentStarred,
            'Dokumen ditandai berbintang.',
            $document,
            $user,
        );
    }

    public function unstar(Document $document, User $user): void
    {
        $dihapus = DocumentStar::query()->where('user_id', $user->id)->where('document_id', $document->id)->delete();

        if ($dihapus === 0) {
            return;
        }

        $this->aktivitas->record(
            ActivityLogName::DocumentWorkspace,
            AuditEvent::DocumentUnstarred,
            'Tanda bintang pada dokumen dihapus.',
            $document,
            $user,
        );
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

    public function trashFolder(DocumentFolder $folder, User $owner): void
    {
        $this->pastikanFolderAktifMilik($folder, $owner);
        $token = (string) Str::uuid();
        $folderIds = $this->folderBesertaTurunan($folder);

        DocumentFolder::query()
            ->whereIn('id', $folderIds)
            ->notTrashed()
            ->update([
                'trashed_at' => now(),
                'trashed_by' => $owner->id,
                'purge_after' => now()->addDays(self::RETENSI_HARI),
                'trash_token' => $token,
            ]);

        $this->aktivitas->record(
            ActivityLogName::DocumentWorkspace,
            AuditEvent::FolderTrashed,
            "Folder \"{$folder->name}\" dipindahkan ke Sampah.",
            $folder,
            $owner,
            ['jumlah_folder' => count($folderIds), 'retensi_hari' => self::RETENSI_HARI],
        );
    }

    public function restoreFolder(DocumentFolder $folder, User $owner): void
    {
        if ($folder->owner_id !== $owner->id || $folder->trash_token === null) {
            throw ValidationException::withMessages(['folder' => 'Folder tidak tersedia.']);
        }

        DocumentFolder::query()
            ->where('trash_token', $folder->trash_token)
            ->where('owner_id', $owner->id)
            ->update(['trashed_at' => null, 'trashed_by' => null, 'purge_after' => null, 'trash_token' => null]);

        $this->aktivitas->record(
            ActivityLogName::DocumentWorkspace,
            AuditEvent::FolderTrashRestored,
            "Folder \"{$folder->name}\" dipulihkan dari Sampah.",
            $folder,
            $owner,
        );
    }

    private function pastikanFolderAktifMilik(DocumentFolder $folder, User $owner): void
    {
        if ($folder->owner_id !== $owner->id || $folder->trashed_at !== null) {
            throw ValidationException::withMessages(['folder' => 'Folder tidak tersedia.']);
        }
    }

    /**
     * Untuk method yang kini boleh dipanggil Editor (bukan cuma pemilik):
     * folder harus aktif DAN pelaku punya hak edit (owner atau role editor
     * langsung/warisan). Menggantikan `pastikanFolderAktifMilik()` di method
     * yang dibuka untuk Editor — `pastikanFolderAktifMilik()` sendiri tetap
     * dipakai method yang owner-only (`trashFolder`).
     */
    private function pastikanFolderAktifBolehDiedit(DocumentFolder $folder, User $pelaku): void
    {
        if ($folder->trashed_at !== null || ! $folder->terlihatSebagaiEditorOleh($pelaku)) {
            throw ValidationException::withMessages(['folder' => 'Folder tidak tersedia.']);
        }
    }

    private function catatPerpindahanDokumen(Document $document, User $owner, string $asal, string $tujuan): void
    {
        $this->aktivitas->record(
            ActivityLogName::DocumentWorkspace,
            AuditEvent::DocumentMoved,
            "Dokumen dipindahkan dari \"{$asal}\" ke \"{$tujuan}\".",
            $document,
            $owner,
            ['lokasi_asal' => $asal, 'lokasi_tujuan' => $tujuan],
        );
    }

    /** @return list<int> */
    private function folderBesertaTurunan(DocumentFolder $folder): array
    {
        $ids = [$folder->id];
        $antrian = [$folder->id];

        while ($antrian !== []) {
            $anak = DocumentFolder::query()
                ->whereIn('parent_id', $antrian)
                ->pluck('id')
                ->map(intval(...))
                ->all();
            $ids = [...$ids, ...$anak];
            $antrian = $anak;
        }

        return $ids;
    }

    private function pastikanKedalaman(DocumentFolder $parent): void
    {
        $kedalaman = 1;
        $node = $parent;
        while ($node->parent_id !== null) {
            $kedalaman++;
            $node = $node->parent()->firstOrFail();
        }

        if ($kedalaman >= DocumentFolder::KEDALAMAN_MAKSIMAL) {
            throw ValidationException::withMessages(['parent_id' => 'Folder hanya dapat memiliki lima tingkat.']);
        }
    }

    private function pastikanNamaTersedia(int $ownerId, ?int $parentId, string $name, ?int $exceptId = null): void
    {
        $ada = DocumentFolder::query()
            ->where('owner_id', $ownerId)
            ->notTrashed()
            ->where('parent_id', $parentId)
            ->where('name_normalized', $this->namaNormal($name))
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
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
