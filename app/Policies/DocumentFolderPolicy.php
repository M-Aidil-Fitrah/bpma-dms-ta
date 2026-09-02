<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DocumentFolder;
use App\Models\User;

/**
 * Fase 1: `view()` mencakup penerima share; `update()`/`delete()`/`share()`
 * owner-only. Fase 2 (Editor, mengikuti Google Drive):
 * - `edit()` (ability baru) — owner ATAU editor. Gerbang untuk mengubah isi
 *   folder (unggah, subfolder, pindah dokumen) dan mengubah nama folder.
 * - `update()` — tetap owner-only. Selain rename lewat controller, ability ini
 *   juga menjaga restore folder dari trash, jadi tidak boleh dibuka ke editor.
 * - `delete()` — tetap owner-only (editor tidak boleh men-trash folder).
 * - `share()` — editor boleh reshare KECUALI `sharing_restricted`.
 * - `restrictSharing()` (ability baru) — owner-only, mengunci pembagian.
 */
final class DocumentFolderPolicy
{
    public function view(User $user, DocumentFolder $folder): bool
    {
        return $folder->terlihatOleh($user);
    }

    /** Mengubah ISI folder (unggah, subfolder, pindah dokumen) DAN mengubah nama folder. */
    public function edit(User $user, DocumentFolder $folder): bool
    {
        return $folder->terlihatSebagaiEditorOleh($user);
    }

    /** Owner-only — juga gerbang restore folder dari trash. */
    public function update(User $user, DocumentFolder $folder): bool
    {
        return $folder->owner_id === $user->id;
    }

    /** Men-trash folder — tetap owner-only. */
    public function delete(User $user, DocumentFolder $folder): bool
    {
        return $folder->owner_id === $user->id;
    }

    public function share(User $user, DocumentFolder $folder): bool
    {
        if ($folder->owner_id === $user->id) {
            return true;
        }

        return ! $folder->sharing_restricted && $folder->terlihatSebagaiEditorOleh($user);
    }

    /** Mengunci/membuka pembagian folder — hanya pemilik. */
    public function restrictSharing(User $user, DocumentFolder $folder): bool
    {
        return $folder->owner_id === $user->id;
    }
}
