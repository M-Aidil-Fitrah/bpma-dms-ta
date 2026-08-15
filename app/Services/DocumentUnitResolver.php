<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * Menentukan unit mana saja yang tercakup ketika pengunggah memilih satu unit.
 *
 * Memilih unit tingkat atas — Sekretaris atau Deputi — secara wajar berarti
 * "beserta divisi di bawahnya". Cascade itu diselesaikan **di sini, saat
 * menyimpan**, dengan menyisipkan tiap divisi sebagai baris tersendiri di
 * `document_units`.
 *
 * Kenapa saat menyimpan dan bukan saat membaca: isi `document_units` jadi
 * mencerminkan persis siapa yang berhak, tanpa aturan tersembunyi yang hanya
 * hidup di dalam query. Akibatnya pengunggah benar-benar dapat mengurangi unit
 * secara manual (FR-39), dan `accessSummary()` selalu menampilkan kenyataan.
 * Kalau cascade juga dihitung ulang saat membaca, menghapus unit induk dari
 * daftar tidak akan berpengaruh apa pun — pengaturan manualnya diam-diam
 * diabaikan (`Catatan_Audit.md` isu #15).
 */
final class DocumentUnitResolver
{
    /**
     * Unit yang disarankan ketika sebuah unit dipilih: unit itu sendiri beserta
     * seluruh divisi aktif di bawahnya.
     *
     * Hanya unit aktif yang disarankan — unit nonaktif tidak lagi muncul
     * sebagai pilihan baru, walau yang sudah tercatat di dokumen lama tetap
     * utuh (`Struktur_Data.md` §3.3).
     *
     * @return Collection<int, Unit>
     */
    public function defaultUnitsFor(Unit $anchor): Collection
    {
        return Unit::query()
            ->active()
            ->where(function ($query) use ($anchor): void {
                $query->whereKey($anchor->getKey())
                    ->orWhere('parent_id', $anchor->getKey());
            })
            ->orderBy('parent_id')
            ->orderBy('nama')
            ->get();
    }

    /**
     * Bentuk siap simpan: daftar id unit hasil cascade dari beberapa unit yang
     * dipilih sekaligus, tanpa duplikat.
     *
     * @param  list<int>  $unitIds
     * @return list<int>
     */
    public function resolveIds(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        $terpilih = Unit::query()->active()->whereKey($unitIds)->get();

        $hasil = $terpilih->pluck('id')
            ->merge(
                Unit::query()->active()
                    ->whereIn('parent_id', $terpilih->pluck('id'))
                    ->pluck('id'),
            );

        return $hasil->unique()->values()->map(intval(...))->all();
    }
}
