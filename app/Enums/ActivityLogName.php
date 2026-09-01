<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kelompok aktivitas yang memang menjadi bagian dari jejak audit DMS.
 *
 * Nilainya sengaja stabil karena tersimpan di basis data dan dipakai sebagai
 * penyaring antarmuka. Jangan mengganti nilai enum tanpa migrasi data.
 */
enum ActivityLogName: string
{
    case Dokumen = 'dokumen';
    case Pengguna = 'pengguna';
    case Unit = 'unit';
    case Jabatan = 'jabatan';
    case Kategori = 'kategori';
    case DocumentShare = 'document_share';
    case DocumentUnit = 'document_unit';
    case DocumentWorkspace = 'document_workspace';
    case FolderShare = 'folder_share';
    case FolderUnit = 'folder_unit';

    public function label(): string
    {
        return match ($this) {
            self::Dokumen => 'Dokumen',
            self::Pengguna => 'Pengguna',
            self::Unit => 'Unit kerja',
            self::Jabatan => 'Jabatan',
            self::Kategori => 'Kategori',
            self::DocumentShare => 'Akses orang tertentu',
            self::DocumentUnit => 'Akses unit',
            self::DocumentWorkspace => 'Ruang kerja dokumen',
            self::FolderShare => 'Akses folder untuk orang tertentu',
            self::FolderUnit => 'Akses folder untuk unit',
        };
    }
}
