<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Membuat turunan visual kecil secara sekali-jadi saat dokumen diunggah.
 *
 * Berkas asli tidak pernah diubah. Gambar mini hanya membantu pengguna
 * mengenali dokumen di grid; kegagalannya tidak boleh menghambat unggahan.
 */
final class DocumentThumbnailService
{
    private const MIME_OFFICE = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/msword',
        'application/vnd.ms-excel',
        'application/vnd.ms-powerpoint',
    ];

    private const MIME_GAMBAR = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
    ];

    public function didukung(string $mime): bool
    {
        return $mime === 'application/pdf'
            || in_array($mime, self::MIME_OFFICE, true)
            || in_array($mime, self::MIME_GAMBAR, true);
    }

    public function generate(Document $document): void
    {
        if (! $this->didukung($document->file_mime_type)) {
            return;
        }

        $disk = Storage::disk('local');
        $sumber = $disk->path($document->file_path);

        if (! is_file($sumber)) {
            throw new RuntimeException("Berkas sumber dokumen {$document->id} tidak ditemukan.");
        }

        $ruangKerja = storage_path('app/dms-thumbnail/'.Str::uuid());
        mkdir($ruangKerja, 0755, true);

        try {
            $nama = Str::uuid()->toString();
            $folder = now()->format('Y/m');
            $thumbnailSementara = $ruangKerja.'/thumbnail.jpg';
            $previewSementara = null;

            if (in_array($document->file_mime_type, self::MIME_GAMBAR, true)) {
                $this->perkecilGambar($sumber, $thumbnailSementara);
            } else {
                $pdf = $document->file_mime_type === 'application/pdf'
                    ? $sumber
                    : $this->konversiOfficeKePdf($sumber, $ruangKerja);

                if ($pdf !== $sumber) {
                    $previewSementara = $pdf;
                }

                $this->renderHalamanPertama($pdf, $thumbnailSementara);
            }

            $thumbnailPath = "thumbnails/{$folder}/{$nama}.jpg";
            $disk->put($thumbnailPath, (string) file_get_contents($thumbnailSementara));

            $kolom = ['thumbnail_path' => $thumbnailPath];

            if ($previewSementara !== null) {
                $previewPath = "previews/{$folder}/{$nama}.pdf";
                $disk->put($previewPath, (string) file_get_contents($previewSementara));
                $kolom['preview_path'] = $previewPath;
            }

            $document->update($kolom);
        } finally {
            $this->hapusRuangKerja($ruangKerja);
        }
    }

    private function konversiOfficeKePdf(string $sumber, string $ruangKerja): string
    {
        $hasil = Process::timeout(60)->run([
            'libreoffice', '--headless', '--convert-to', 'pdf:writer_pdf_Export', '--outdir', $ruangKerja, $sumber,
        ]);

        if ($hasil->failed()) {
            throw new RuntimeException('LibreOffice gagal mengonversi dokumen: '.$hasil->errorOutput());
        }

        $berkas = glob($ruangKerja.'/*.pdf');

        if ($berkas === false || count($berkas) !== 1) {
            throw new RuntimeException('LibreOffice tidak menghasilkan satu berkas PDF.');
        }

        return $berkas[0];
    }

    private function renderHalamanPertama(string $pdf, string $tujuan): void
    {
        $hasil = Process::timeout(60)->run([
            'gs', '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=jpeg', '-dJPEGQ=85',
            '-r150', '-dFirstPage=1', '-dLastPage=1', "-sOutputFile={$tujuan}", $pdf,
        ]);

        if ($hasil->failed() || ! is_file($tujuan)) {
            throw new RuntimeException('Ghostscript gagal merender halaman pertama: '.$hasil->errorOutput());
        }
    }

    private function perkecilGambar(string $sumber, string $tujuan): void
    {
        $gambar = @imagecreatefromstring((string) file_get_contents($sumber));

        if ($gambar === false) {
            throw new RuntimeException('PHP GD tidak dapat membaca gambar sumber.');
        }

        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);
        $skala = min(320 / $lebar, 180 / $tinggi, 1);
        $lebarTarget = max(1, (int) round($lebar * $skala));
        $tinggiTarget = max(1, (int) round($tinggi * $skala));
        $kanvas = imagecreatetruecolor(320, 180);
        imagefill($kanvas, 0, 0, imagecolorallocate($kanvas, 244, 247, 247));
        imagecopyresampled($kanvas, $gambar, (int) ((320 - $lebarTarget) / 2), (int) ((180 - $tinggiTarget) / 2), 0, 0, $lebarTarget, $tinggiTarget, $lebar, $tinggi);
        imagejpeg($kanvas, $tujuan, 85);
        imagedestroy($gambar);
        imagedestroy($kanvas);
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
