<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Meraster PDF pindaian satu halaman demi satu halaman untuk Tesseract.
 *
 * Tidak pernah menyimpan hasil raster ke disk dokumen. Halaman sementara
 * hidup hanya sepanjang job agar OCR dapat melaporkan progres yang benar dan
 * agar PDF besar tidak sekaligus memenuhi cakram sementara. Jumlah halaman
 * didapat dari `DocumentTextExtractor::pdfTeksDanHalaman()` (satu parse yang
 * sama dipakai untuk cek teks digital), bukan diparse ulang di sini.
 */
final class ScannedPdfOcr
{
    public function __construct(private readonly DocumentTextExtractor $ekstraktor) {}

    /**
     * @param  callable(int, int): void  $laporkanProgres
     */
    public function extract(string $path, int $jumlahHalaman, callable $laporkanProgres): string
    {
        $ruangKerja = storage_path('app/dms-pdf-ocr/'.Str::uuid());
        mkdir($ruangKerja, 0700, true);

        try {
            $bagian = [];

            for ($halaman = 1; $halaman <= $jumlahHalaman; $halaman++) {
                $raster = "{$ruangKerja}/halaman-{$halaman}.png";
                $this->renderHalaman($path, $halaman, $raster);

                $teks = $this->ekstraktor->gambar($raster);

                if ($teks !== '') {
                    $bagian[] = $teks;
                }

                $laporkanProgres($halaman, $jumlahHalaman);
                unlink($raster);
            }

            return trim(implode("\n\n", $bagian));
        } finally {
            $this->hapusRuangKerja($ruangKerja);
        }
    }

    private function renderHalaman(string $pdf, int $halaman, string $tujuan): void
    {
        $hasil = Process::timeout((int) config('dms.ekstraksi.pdf_ocr_timeout_per_halaman_detik'))
            ->run([
                'gs', '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=pnggray',
                '-r'.config('dms.ekstraksi.pdf_ocr_dpi'),
                "-dFirstPage={$halaman}", "-dLastPage={$halaman}",
                "-sOutputFile={$tujuan}", $pdf,
            ]);

        if ($hasil->failed() || ! is_file($tujuan)) {
            throw new RuntimeException("Ghostscript gagal meraster halaman {$halaman}: ".$hasil->errorOutput());
        }
    }

    private function hapusRuangKerja(string $ruangKerja): void
    {
        if (! is_dir($ruangKerja)) {
            return;
        }

        foreach (scandir($ruangKerja) ?: [] as $berkas) {
            if ($berkas !== '.' && $berkas !== '..') {
                unlink($ruangKerja.'/'.$berkas);
            }
        }

        rmdir($ruangKerja);
    }
}
