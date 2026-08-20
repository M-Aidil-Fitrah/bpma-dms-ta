<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Enums\ExtractionStatus;
use App\Services\DocumentTextExtractor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menyalin berkas contoh ke penyimpanan, meniru persis perilaku
 * `DocumentUploadService` yang dibangun pada FEAT-09.
 *
 * Sengaja meniru, bukan memakai jalur pintas: kalau seed menaruh berkas dengan
 * pola penamaan atau folder yang berbeda dari unggahan sungguhan, pratinjau dan
 * unduhan akan bekerja untuk data seed tapi gagal untuk dokumen yang diunggah
 * pengguna — atau sebaliknya. Perbedaan semacam itu baru ketahuan saat demo.
 */
final class BerkasContoh
{
    private const FOLDER_SUMBER = __DIR__.'/../files';

    /** Berkas demo dipisahkan dari berkas yang benar-benar diunggah pengguna. */
    private const FOLDER_TUJUAN = 'documents/seed';

    /**
     * Katalog berkas contoh beserta perilaku ekstraksi yang diharapkan.
     *
     * Kolom `teks` diisi langsung di sini, bukan lewat job antrian: hanya ada
     * sebelas berkas yang dipakai bergiliran oleh ratusan dokumen, sehingga
     * mengekstraknya berulang kali hanya memperlambat `migrate:fresh --seed`
     * tanpa membuktikan apa pun.
     *
     * @var array<string, array{mime: string, status: ExtractionStatus, teks: bool}>
     */
    public const KATALOG = [
        'sop-pengendalian-dokumen.pdf' => [
            'mime' => 'application/pdf',
            'status' => ExtractionStatus::Completed,
            'teks' => true,
        ],
        'laporan-realisasi-anggaran.pdf' => [
            'mime' => 'application/pdf',
            'status' => ExtractionStatus::Completed,
            'teks' => true,
        ],
        // PDF hasil pindaian: OCR tidak menemukan teks yang layak diindeks.
        // Berkas tetap dapat diunduh, tetapi tidak boleh dilabeli selesai dan
        // dapat dicari.
        'nota-dinas-hasil-pindai.pdf' => [
            'mime' => 'application/pdf',
            'status' => ExtractionStatus::ReviewRequired,
            'teks' => false,
        ],
        'notulen-rapat-koordinasi.docx' => [
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'status' => ExtractionStatus::Completed,
            'teks' => true,
        ],
        'rencana-kerja-anggaran.docx' => [
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'status' => ExtractionStatus::Completed,
            'teks' => true,
        ],
        'daftar-inventaris-aset.txt' => [
            'mime' => 'text/plain',
            'status' => ExtractionStatus::Completed,
            'teks' => true,
        ],
        'nota-dinas-foto.jpg' => [
            'mime' => 'image/jpeg',
            'status' => ExtractionStatus::Completed,
            'teks' => true,
        ],
        'papan-informasi-lapangan.png' => [
            'mime' => 'image/png',
            'status' => ExtractionStatus::Completed,
            'teks' => true,
        ],
        // Foto tanpa naskah: OCR berjalan, tetapi tidak layak untuk pencarian.
        'foto-fasilitas-produksi.jpg' => [
            'mime' => 'image/jpeg',
            'status' => ExtractionStatus::ReviewRequired,
            'teks' => false,
        ],
        // Tipe tak didukung: tidak ada job yang pernah dibuat.
        'rekaman-rapat.mp4' => [
            'mime' => 'video/mp4',
            'status' => ExtractionStatus::NotApplicable,
            'teks' => false,
        ],
        'arsip-lampiran-pendukung.zip' => [
            'mime' => 'application/zip',
            'status' => ExtractionStatus::NotApplicable,
            'teks' => false,
        ],
    ];

    /** Mengosongkan berkas demo lama tanpa pernah menyentuh unggahan pengguna. */
    public static function bersihkanPenyimpananSeed(): void
    {
        Storage::disk('local')->deleteDirectory(self::FOLDER_TUJUAN);
    }

    /**
     * Menyalin satu berkas contoh ke penyimpanan dengan nama acak.
     *
     * @return array{file_path: string, file_name_original: string, file_mime_type: string, file_size: int}
     */
    public static function salin(string $namaBerkas, string $tahun, string $bulan): array
    {
        $sumber = self::FOLDER_SUMBER.'/'.$namaBerkas;

        if (! is_file($sumber)) {
            throw new RuntimeException(
                "Berkas contoh '{$namaBerkas}' tidak ditemukan. Jalankan "
                .'`bash database/seeders/files/generate.sh` terlebih dulu.'
            );
        }

        $ekstensi = pathinfo($namaBerkas, PATHINFO_EXTENSION);
        $tujuan = sprintf('%s/%s/%s/%s.%s', self::FOLDER_TUJUAN, $tahun, $bulan, Str::uuid(), $ekstensi);

        Storage::disk('local')->put($tujuan, (string) file_get_contents($sumber));

        return [
            'file_path' => $tujuan,
            'file_name_original' => $namaBerkas,
            'file_mime_type' => self::KATALOG[$namaBerkas]['mime'],
            'file_size' => (int) filesize($sumber),
        ];
    }

    /**
     * Isi teks hasil ekstraksi untuk sebuah berkas contoh.
     *
     * Dibaca sekali lalu disimpan di memori, karena berkas yang sama dipakai
     * bergiliran oleh ratusan dokumen.
     */
    public static function teksEkstraksi(string $namaBerkas): ?string
    {
        static $cache = [];

        if (! self::KATALOG[$namaBerkas]['teks']) {
            return null;
        }

        return $cache[$namaBerkas] ??= self::bacaTeks($namaBerkas);
    }

    private static function bacaTeks(string $namaBerkas): string
    {
        $sumber = self::FOLDER_SUMBER.'/'.$namaBerkas;
        $ekstensi = pathinfo($namaBerkas, PATHINFO_EXTENSION);
        $ekstraktor = new DocumentTextExtractor;

        return match ($ekstensi) {
            'pdf' => $ekstraktor->pdf($sumber),
            'txt' => $ekstraktor->txt($sumber),
            'docx' => $ekstraktor->docx($sumber),
            default => self::bacaGambar($sumber),
        };
    }

    private static function bacaGambar(string $sumber): string
    {
        // OCR dijalankan sekali per berkas contoh saat seeding. Kalau Tesseract
        // belum terpasang, seeding tetap berjalan — hanya isi teksnya kosong,
        // bukan seluruh proses yang gagal.
        try {
            return (new DocumentTextExtractor)->gambar($sumber)->text;
        } catch (\Throwable) {
            return '';
        }
    }
}
