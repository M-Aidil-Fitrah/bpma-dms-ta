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
     * `$unit`/`$pengguna` boleh berupa daftar id polos (`list<int>` — tiap id
     * diperlakukan sebagai `role='viewer'`, kompat Fase 1) atau map
     * `array<int, string>` id → `'viewer'|'editor'` (Fase 2). Keduanya
     * dinormalisasi ke map id → role sebelum diteruskan ke `PivotAccessSync`.
     *
     * @param  list<int>|array<int, string>  $unit
     * @param  list<int>|array<int, string>  $pengguna
     */
    public function sinkron(
        DocumentFolder $folder,
        array $unit,
        array $pengguna,
        User $oleh,
    ): DocumentAccessChanges {
        $unitRoles = $this->normalisasiPeran($unit);
        $penggunaRoles = $this->normalisasiPeran($pengguna);

        return $this->sync->sinkron(
            $folder->targetUnits(),
            $folder->sharedUsers(),
            array_keys($unitRoles),
            array_keys($penggunaRoles),
            $oleh,
            $unitRoles,
            $penggunaRoles,
        );
    }

    /**
     * @param  list<int>|array<int, string>  $input
     * @return array<int, string>
     */
    private function normalisasiPeran(array $input): array
    {
        if ($input === []) {
            return [];
        }

        if (array_is_list($input)) {
            return array_fill_keys(array_map(intval(...), $input), 'viewer');
        }

        $hasil = [];

        foreach ($input as $id => $role) {
            $hasil[(int) $id] = $role;
        }

        return $hasil;
    }
}
