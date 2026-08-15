<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * Operasi pohon unit yang dipakai controller dan validasi.
 *
 * Seluruh parent dimuat sekali ke memori. Menanyakan parent di dalam loop akan
 * tampak baik pada 20 unit seed, tetapi berubah menjadi N+1 begitu struktur
 * organisasi berkembang.
 */
final class UnitHierarchy
{
    /**
     * @param  Collection<int, Unit>  $units
     * @return array<int, int>
     */
    public function kedalaman(Collection $units): array
    {
        $parent = $units->mapWithKeys(
            static fn (Unit $unit): array => [$unit->id => $unit->parent_id],
        )->all();

        $hasil = [];

        foreach (array_keys($parent) as $id) {
            $hasil[$id] = $this->hitungKedalaman($id, $parent, $hasil, []);
        }

        return $hasil;
    }

    /** Apakah memilih `$parentId` akan menjadikan `$unit` induk dirinya sendiri. */
    public function membentukSiklus(Unit $unit, ?int $parentId): bool
    {
        if ($parentId === null) {
            return false;
        }

        $parent = Unit::query()->pluck('parent_id', 'id')->all();
        $cursor = $parentId;
        $dikunjungi = [];

        while ($cursor !== null && ! isset($dikunjungi[$cursor])) {
            if ($cursor === $unit->id) {
                return true;
            }

            $dikunjungi[$cursor] = true;
            $cursor = $parent[$cursor] ?? null;
        }

        // Pohon yang sudah korup juga tidak boleh diperparah dengan perubahan
        // baru. Normalnya cabang ini tidak pernah terlewati karena validasi
        // selalu dijalankan sejak unit pertama dibuat.
        return $cursor !== null;
    }

    /**
     * @param  Collection<int, Unit>  $units
     * @return Collection<int, Unit>
     */
    public function kandidatInduk(Collection $units, ?Unit $sedangDiubah = null): Collection
    {
        if ($sedangDiubah === null) {
            return $units;
        }

        $terlarang = $this->keturunan($sedangDiubah->id, $units);
        $terlarang[] = $sedangDiubah->id;

        return $units->reject(static fn (Unit $unit): bool => in_array($unit->id, $terlarang, true));
    }

    /**
     * @param  array<int, int|null>  $parent
     * @param  array<int, int>  $memo
     * @param  array<int, true>  $jalur
     */
    private function hitungKedalaman(int $id, array $parent, array &$memo, array $jalur): int
    {
        if (isset($memo[$id])) {
            return $memo[$id];
        }

        if (isset($jalur[$id])) {
            return 0;
        }

        $induk = $parent[$id] ?? null;

        if ($induk === null || ! array_key_exists($induk, $parent)) {
            return 0;
        }

        $jalur[$id] = true;

        return 1 + $this->hitungKedalaman($induk, $parent, $memo, $jalur);
    }

    /**
     * @param  Collection<int, Unit>  $units
     * @return list<int>
     */
    private function keturunan(int $id, Collection $units): array
    {
        $anak = $units->groupBy('parent_id');
        $hasil = [];
        $antrian = [$id];

        while ($antrian !== []) {
            $induk = array_shift($antrian);

            foreach ($anak->get($induk, collect()) as $anakUnit) {
                $hasil[] = $anakUnit->id;
                $antrian[] = $anakUnit->id;
            }
        }

        return $hasil;
    }
}
