<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Jabatan;
use Illuminate\Database\Seeder;

/**
 * Lima jenjang jabatan awal BPMA.
 *
 * `tingkat_akses` 1 adalah yang tertinggi. Angka ini dipakai mekanisme akses
 * "jenjang jabatan": dokumen ber-`min_tingkat_akses` 2 terlihat oleh siapa pun
 * bertingkat 1 dan 2, lintas unit.
 */
final class JabatanSeeder extends Seeder
{
    /**
     * @var list<array{nama: string, tingkat_akses: int}>
     */
    private const JABATAN = [
        ['nama' => 'Pimpinan BPMA', 'tingkat_akses' => 1],
        ['nama' => 'Sekretaris', 'tingkat_akses' => 2],
        ['nama' => 'Deputi', 'tingkat_akses' => 2],
        ['nama' => 'Kepala Divisi', 'tingkat_akses' => 3],
        ['nama' => 'Anggota', 'tingkat_akses' => 4],
    ];

    public function run(): void
    {
        foreach (self::JABATAN as $jabatan) {
            Jabatan::updateOrCreate(
                ['nama' => $jabatan['nama']],
                ['tingkat_akses' => $jabatan['tingkat_akses'], 'is_active' => true],
            );
        }
    }
}
