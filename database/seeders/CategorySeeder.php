<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Kategori dokumen awal, disesuaikan dengan urusan badan pengelola migas.
 *
 * Superadmin dapat menambah, mengubah, dan menonaktifkannya lewat antarmuka
 * tanpa deploy ulang (FR-14) — daftar ini hanya titik awal.
 */
final class CategorySeeder extends Seeder
{
    /**
     * Kode dipakai untuk menyusun nomor dokumen pada seed (FEAT-04).
     *
     * @var array<string, array{kode: string, deskripsi: string}>
     */
    public const KATEGORI = [
        'Peraturan & Kebijakan' => [
            'kode' => 'PER',
            'deskripsi' => 'Peraturan kepala badan, kebijakan internal, dan pedoman umum.',
        ],
        'SOP & Panduan Kerja' => [
            'kode' => 'SOP',
            'deskripsi' => 'Prosedur operasional baku dan panduan teknis pelaksanaan pekerjaan.',
        ],
        'Kontrak & Perjanjian' => [
            'kode' => 'KTR',
            'deskripsi' => 'Kontrak kerja sama, perjanjian jasa, dan nota kesepahaman.',
        ],
        'Laporan Keuangan' => [
            'kode' => 'LKU',
            'deskripsi' => 'Laporan realisasi anggaran dan laporan keuangan berkala.',
        ],
        'Dokumen Perencanaan & Anggaran' => [
            'kode' => 'RKA',
            'deskripsi' => 'Rencana kerja, rencana anggaran, dan dokumen program.',
        ],
        'Laporan Operasi & Produksi' => [
            'kode' => 'LOP',
            'deskripsi' => 'Laporan lifting, produksi, dan kegiatan operasi lapangan.',
        ],
        'Data Teknis & Eksplorasi' => [
            'kode' => 'TEK',
            'deskripsi' => 'Data seismik, laporan sumur, dan kajian teknis lapangan.',
        ],
        'Dokumen Audit & Pengawasan' => [
            'kode' => 'AUD',
            'deskripsi' => 'Laporan hasil audit, temuan pengawasan, dan tindak lanjutnya.',
        ],
        'Notulen Rapat' => [
            'kode' => 'NTR',
            'deskripsi' => 'Notulen rapat koordinasi, rapat pimpinan, dan rapat teknis.',
        ],
        'Surat Menyurat' => [
            'kode' => 'SRT',
            'deskripsi' => 'Nota dinas, surat undangan, dan surat keterangan.',
        ],
    ];

    public function run(): void
    {
        foreach (self::KATEGORI as $nama => $data) {
            Category::updateOrCreate(
                ['nama' => $nama],
                ['deskripsi' => $data['deskripsi'], 'is_active' => true],
            );
        }
    }
}
