<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kode aksi audit, terpisah dari kalimat yang ditampilkan kepada pengguna.
 *
 * Spatie menyimpan event sebagai string bebas. Enum ini menutup risiko typo
 * yang akan memecah penyaring riwayat atau membuat satu tindakan punya dua
 * kode berbeda di tempat yang berbeda.
 */
enum AuditEvent: string
{
    case DocumentUploaded = 'document_uploaded';
    case DocumentUpdated = 'document_updated';
    case DocumentViewed = 'document_viewed';
    case DocumentDownloaded = 'document_downloaded';
    case DocumentDeactivated = 'document_deactivated';
    case DocumentRestored = 'document_restored';
    case DocumentTrashed = 'document_trashed';
    case DocumentTrashRestored = 'document_trash_restored';
    case DocumentMoved = 'document_moved';
    case DocumentStarred = 'document_starred';
    case DocumentUnstarred = 'document_unstarred';
    case FolderCreated = 'folder_created';
    case FolderRenamed = 'folder_renamed';
    case FolderTrashed = 'folder_trashed';
    case FolderTrashRestored = 'folder_trash_restored';
    case DocumentPurged = 'document_purged';
    case DocumentReplaced = 'document_replaced';
    case DocumentVersionRestored = 'document_version_restored';
    case DocumentStatusChanged = 'document_status_changed';
    case AccessGranted = 'access_granted';
    case AccessRevoked = 'access_revoked';
    case Created = 'created';
    case Updated = 'updated';
    case Deactivated = 'deactivated';
    case Restored = 'restored';
    case PasswordReset = 'password_reset';
}
