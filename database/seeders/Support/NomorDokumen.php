<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Models\Unit;
use Database\Seeders\UnitSeeder;

/**
 * Menyusun nomor dokumen mengikuti kaidah penomoran surat dinas instansi.
 *
 *   042/BPMA/DPR-TPL/VIII/2026
 *   │   │    │       │    └── tahun
 *   │   │    │       └─────── bulan dalam angka romawi
 *   │   │    └─────────────── kode unit induk dan divisi penerbit
 *   │   └──────────────────── kode instansi
 *   └──────────────────────── nomor urut, dihitung ulang per unit per tahun
 *
 * Unit tingkat atas tidak memiliki bagian divisi, sehingga nomornya berbentuk
 * `003/BPMA/DOP/I/2026`.
 */
final class NomorDokumen
{
    private const ROMAWI = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    /**
     * Nomor urut terakhir per kombinasi unit dan tahun.
     *
     * @var array<string, int>
     */
    private array $urut = [];

    public function berikutnya(Unit $unit, string $tanggal): string
    {
        $waktu = strtotime($tanggal);
        $tahun = (int) date('Y', $waktu);
        $bulan = (int) date('n', $waktu);

        $kunci = $unit->id.'-'.$tahun;
        $this->urut[$kunci] = ($this->urut[$kunci] ?? 0) + 1;

        return sprintf(
            '%03d/BPMA/%s/%s/%d',
            $this->urut[$kunci],
            self::kodeUnit($unit),
            self::ROMAWI[$bulan],
            $tahun,
        );
    }

    /**
     * Kode divisi selalu didahului kode induknya, supaya nomor tetap
     * menunjukkan jalur organisasi penerbitnya — bukan sekadar divisi lepas.
     */
    private static function kodeUnit(Unit $unit): string
    {
        $kode = UnitSeeder::KODE[$unit->nama] ?? 'UMM';

        if ($unit->parent_id === null) {
            return $kode;
        }

        $induk = $unit->relationLoaded('parent') ? $unit->parent : $unit->parent()->first();
        $kodeInduk = $induk === null ? null : (UnitSeeder::KODE[$induk->nama] ?? null);

        return $kodeInduk === null ? $kode : "{$kodeInduk}-{$kode}";
    }
}
