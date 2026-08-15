<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Dua puluh unit kerja BPMA sesuai bagan organisasi resmi.
 *
 * Disisipkan dua tahap: lima unit tingkat atas lebih dulu, baru lima belas
 * divisi yang merujuk `id` induknya.
 */
final class UnitSeeder extends Seeder
{
    /**
     * Kode singkat unit, dipakai untuk menyusun nomor surat dinas pada seed
     * dokumen (FEAT-04). Tidak disimpan sebagai kolom karena hanya dibutuhkan
     * saat pembuatan data contoh, bukan oleh aplikasi.
     *
     * @var array<string, string>
     */
    public const KODE = [
        'Sekretaris BPMA' => 'SES',
        'Divisi SDM dan Umum' => 'SDM',
        'Divisi Keuangan Internal' => 'KEU',
        'Divisi Hukum, Program & Pelaporan' => 'HPP',

        'Deputi Perencanaan' => 'DPR',
        'Divisi Perencanaan Eksplorasi dan Eksploitasi' => 'PEE',
        'Divisi Teknologi dan Pengembangan Lapangan' => 'TPL',
        'Divisi Pengendalian Program dan Anggaran' => 'PPA',

        'Deputi Operasi' => 'DOP',
        'Divisi Operasi Produksi' => 'OPP',
        'Divisi Perawatan Fasilitas dan Pengendalian Proyek' => 'PFP',
        'Divisi Penunjang Operasi' => 'PNO',

        'Deputi Keuangan dan Monetisasi' => 'DKM',
        'Divisi Akuntansi, Perpajakan dan Manajemen Risiko' => 'APR',
        'Divisi Audit Kontraktor Kontrak Kerja Sama Eksplorasi & Eksploitasi' => 'AKK',
        'Divisi Monetisasi Minyak dan Gas Bumi' => 'MMG',

        'Deputi Dukungan Bisnis' => 'DDB',
        'Divisi Pengelolaan Aset dan Rantai Suplai' => 'ARS',
        'Divisi Formalitas, Hubungan Eksternal dan Sekuriti K3KS' => 'FHS',
        'Divisi Manajemen Sistem Teknologi Informasi' => 'MSTI',
    ];

    /**
     * Unit tingkat atas beserta divisi di bawahnya.
     *
     * @var array<string, array{tipe: string, divisi: list<string>}>
     */
    private const STRUKTUR = [
        'Sekretaris BPMA' => [
            'tipe' => Unit::TIPE_SEKRETARIS,
            'divisi' => [
                'Divisi SDM dan Umum',
                'Divisi Keuangan Internal',
                'Divisi Hukum, Program & Pelaporan',
            ],
        ],
        'Deputi Perencanaan' => [
            'tipe' => Unit::TIPE_DEPUTI,
            'divisi' => [
                'Divisi Perencanaan Eksplorasi dan Eksploitasi',
                'Divisi Teknologi dan Pengembangan Lapangan',
                'Divisi Pengendalian Program dan Anggaran',
            ],
        ],
        'Deputi Operasi' => [
            'tipe' => Unit::TIPE_DEPUTI,
            'divisi' => [
                'Divisi Operasi Produksi',
                'Divisi Perawatan Fasilitas dan Pengendalian Proyek',
                'Divisi Penunjang Operasi',
            ],
        ],
        'Deputi Keuangan dan Monetisasi' => [
            'tipe' => Unit::TIPE_DEPUTI,
            'divisi' => [
                'Divisi Akuntansi, Perpajakan dan Manajemen Risiko',
                'Divisi Audit Kontraktor Kontrak Kerja Sama Eksplorasi & Eksploitasi',
                'Divisi Monetisasi Minyak dan Gas Bumi',
            ],
        ],
        'Deputi Dukungan Bisnis' => [
            'tipe' => Unit::TIPE_DEPUTI,
            'divisi' => [
                'Divisi Pengelolaan Aset dan Rantai Suplai',
                'Divisi Formalitas, Hubungan Eksternal dan Sekuriti K3KS',
                'Divisi Manajemen Sistem Teknologi Informasi',
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::STRUKTUR as $namaInduk => $data) {
            $induk = Unit::updateOrCreate(
                ['nama' => $namaInduk],
                ['parent_id' => null, 'tipe' => $data['tipe'], 'is_active' => true],
            );

            foreach ($data['divisi'] as $namaDivisi) {
                Unit::updateOrCreate(
                    ['nama' => $namaDivisi],
                    [
                        'parent_id' => $induk->id,
                        'tipe' => Unit::TIPE_DIVISI,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
