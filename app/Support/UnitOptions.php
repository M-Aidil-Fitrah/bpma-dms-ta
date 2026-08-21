<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * Susunan unit kerja aktif yang dipakai bersama oleh setiap opsi filter/
 * formulir berbasis unit (dokumen, pengguna, log aktivitas admin).
 */
final class UnitOptions
{
    /**
     * Unit tingkat atas selalu segera diikuti divisinya. Mengurutkan seluruh
     * nama secara datar membuat Sekretaris atau Deputi muncul jauh dari
     * divisinya sendiri dan mudah salah pilih pada dropdown.
     *
     * @return Collection<int, Unit>
     */
    public static function aktifTerurut(): Collection
    {
        $unit = Unit::query()
            ->active()
            ->with('parent:id,nama')
            ->get(['id', 'nama', 'parent_id', 'tipe']);
        $anakPerInduk = $unit->whereNotNull('parent_id')->groupBy('parent_id');

        return $unit
            ->whereNull('parent_id')
            ->sortBy(fn (Unit $induk): string => ($induk->tipe === Unit::TIPE_SEKRETARIS ? '0' : '1').$induk->nama)
            ->flatMap(fn (Unit $induk) => collect([$induk])->concat(
                $anakPerInduk->get($induk->id, collect())->sortBy('nama'),
            ))
            ->values();
    }

    /**
     * Bentuk pohon untuk `UnitTreeSelect` — nama TIDAK digabung dengan
     * induknya di sini, komponennya sendiri yang menyusun hierarkinya lewat
     * `parent_id`.
     *
     * @return list<array{id: int, nama: string, parent_id: int|null}>
     */
    public static function pohon(): array
    {
        return self::aktifTerurut()
            ->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'nama' => $unit->nama,
                'parent_id' => $unit->parent_id,
            ])
            ->all();
    }

    /**
     * Daftar datar dengan nama induk disertakan — dipakai mencari label chip
     * filter yang sedang aktif, supaya "Divisi Keuangan Internal" dapat
     * dibedakan dari divisi bernama mirip di deputi lain.
     *
     * @return list<array{id: int, nama: string}>
     */
    public static function berlabel(): array
    {
        return self::aktifTerurut()
            ->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'nama' => $unit->parent === null
                    ? $unit->nama
                    : "{$unit->parent->nama} — {$unit->nama}",
            ])
            ->all();
    }
}
