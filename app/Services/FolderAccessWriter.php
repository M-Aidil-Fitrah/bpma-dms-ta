<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\DocumentAccessChanges;
use App\Models\DocumentFolder;
use App\Models\User;

/**
 * Menulis daftar unit dan orang yang berhak melihat sebuah folder Dokumen
 * Saya. Mekanisme diff/sync sesungguhnya ada di `PivotAccessSync` — lihat
 * dokumentasinya untuk alasan `attach()`/`detach()` dipilih alih-alih
 * `sync()`.
 */
final class FolderAccessWriter
{
    public function __construct(private readonly PivotAccessSync $sync) {}

    /**
     * @param  list<int>  $unitIds
     * @param  list<int>  $penerimaIds
     */
    public function sinkron(
        DocumentFolder $folder,
        array $unitIds,
        array $penerimaIds,
        User $oleh,
    ): DocumentAccessChanges {
        return $this->sync->sinkron(
            $folder->targetUnits(),
            $folder->sharedUsers(),
            $unitIds,
            $penerimaIds,
            $oleh,
        );
    }
}
