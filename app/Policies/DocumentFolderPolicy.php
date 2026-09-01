<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DocumentFolder;
use App\Models\User;

/**
 * Update dan delete tetap owner-only (Fase 1 tidak memberi hak ubah kepada
 * penerima share). `view()` sekarang juga true untuk penerima share — lihat
 * `DocumentFolder::terlihatOleh()` untuk aturan warisannya.
 */
final class DocumentFolderPolicy
{
    public function view(User $user, DocumentFolder $folder): bool
    {
        return $folder->terlihatOleh($user);
    }

    public function update(User $user, DocumentFolder $folder): bool
    {
        return $folder->owner_id === $user->id;
    }

    public function delete(User $user, DocumentFolder $folder): bool
    {
        return $folder->owner_id === $user->id;
    }

    /** Hanya pemilik yang mengelola daftar akses — penerima share tidak boleh reshare (Fase 1). */
    public function share(User $user, DocumentFolder $folder): bool
    {
        return $folder->owner_id === $user->id;
    }
}
