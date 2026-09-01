<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\DocumentAccessChanges;
use App\Models\Document;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menulis daftar unit dan orang yang berhak melihat sebuah dokumen (FR-42).
 *
 * Mekanisme diff/sync-nya ada di `PivotAccessSync` (dipakai bersama
 * `FolderAccessWriter`). Kelas ini tinggal menghubungkan relasi milik
 * `Document`, plus `perubahanAntar()` yang KHUSUS dokumen (bandingkan akses
 * antar versi dalam satu rantai) — folder tidak punya konsep versi, jadi
 * method itu tidak ikut diekstrak.
 */
final class DocumentAccessWriter
{
    public function __construct(private readonly PivotAccessSync $sync) {}

    /**
     * @param  list<int>  $unitIds
     * @param  list<int>  $penerimaIds
     */
    public function sinkron(
        Document $document,
        array $unitIds,
        array $penerimaIds,
        User $oleh,
    ): DocumentAccessChanges {
        return $this->sync->sinkron(
            $document->targetUnits(),
            $document->sharedUsers(),
            $unitIds,
            $penerimaIds,
            $oleh,
        );
    }

    /**
     * Menghitung perubahan efektif antara dua snapshot versi yang berurutan.
     *
     * Revisi baru selalu diawali relasi kosong, sehingga nilai balik `sinkron()`
     * pada baris baru akan keliru bila dibaca sebagai audit: seluruh target
     * tampak "ditambahkan". Audit harus membandingkan snapshot pendahulu dan
     * penerus agar pencabutan tetap terlihat sebagai pencabutan.
     */
    public function perubahanAntar(Document $sebelum, Document $sesudah): DocumentAccessChanges
    {
        $unitSebelum = $sebelum->targetUnits()->get(['units.id', 'units.nama'])->keyBy('id');
        $unitSesudah = $sesudah->targetUnits()->get(['units.id', 'units.nama'])->keyBy('id');
        $penggunaSebelum = $sebelum->sharedUsers()->get(['users.id', 'users.name'])->keyBy('id');
        $penggunaSesudah = $sesudah->sharedUsers()->get(['users.id', 'users.name'])->keyBy('id');

        return new DocumentAccessChanges(
            unitDitambahkan: $this->ringkasTarget($unitSesudah, $unitSebelum, 'nama'),
            unitDicabut: $this->ringkasTarget($unitSebelum, $unitSesudah, 'nama'),
            penggunaDitambahkan: $this->ringkasTarget($penggunaSesudah, $penggunaSebelum, 'name'),
            penggunaDicabut: $this->ringkasTarget($penggunaSebelum, $penggunaSesudah, 'name'),
        );
    }

    /**
     * @param  Collection<int, Unit|User>  $utama
     * @param  Collection<int, Unit|User>  $pembanding
     * @return list<array{id: int, nama: string}>
     */
    private function ringkasTarget($utama, $pembanding, string $kolomNama): array
    {
        return $utama
            ->reject(fn (Unit|User $model): bool => $pembanding->has($model->id))
            ->sortBy($kolomNama)
            ->map(fn (Unit|User $model): array => ['id' => $model->id, 'nama' => $model->{$kolomNama}])
            ->values()
            ->all();
    }
}
