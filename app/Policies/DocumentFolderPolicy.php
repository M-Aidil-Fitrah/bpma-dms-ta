<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DocumentFolder;
use App\Models\User;

/** Folder Dokumen Saya tidak pernah dibuka atau dikelola lintas pemilik. */
final class DocumentFolderPolicy
{
    public function view(User $user, DocumentFolder $folder): bool
    {
        return $folder->owner_id === $user->id;
    }

    public function update(User $user, DocumentFolder $folder): bool
    {
        return $this->view($user, $folder);
    }

    public function delete(User $user, DocumentFolder $folder): bool
    {
        return $this->view($user, $folder);
    }
}
