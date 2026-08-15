<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExtractionStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menyimpan berkas unggahan ke disk dan menentukan perlakuan ekstraksinya.
 *
 * Pola jalur di sini WAJIB sama persis dengan yang dipakai
 * `Database\Seeders\Support\BerkasContoh` — `documents/{Y}/{m}/{uuid}.{ext}`
 * pada disk `local`. Kalau berbeda, pratinjau dan unduhan akan bekerja untuk
 * data seed tapi gagal untuk unggahan sungguhan (atau sebaliknya), dan
 * perbedaan semacam itu baru ketahuan saat demo.
 */
final class DocumentUploadService
{
    /**
     * Menyimpan berkas dan mengembalikan metadata untuk kolom `documents`.
     *
     * @return array{
     *     file_path: string,
     *     file_name_original: string,
     *     file_mime_type: string,
     *     file_size: int,
     *     extraction_status: ExtractionStatus,
     * }
     */
    public function store(UploadedFile $file): array
    {
        // Tipe MIME dibaca dari isi berkas, bukan dari yang dikirim peramban.
        // Header `Content-Type` sepenuhnya dikendalikan klien dan dapat
        // dipalsukan — memakainya berarti mempercayai pihak yang justru sedang
        // kita periksa.
        $mime = $file->getMimeType() ?? 'application/octet-stream';

        $path = $file->storeAs(
            $this->folder(),
            $this->namaAcak($file),
            'local',
        );

        return [
            'file_path' => $path,
            // Nama asli disimpan apa adanya untuk ditampilkan dan dipakai saat
            // mengunduh. Ia tidak pernah menjadi bagian jalur di disk, sehingga
            // nama seperti "../../etc/passwd" tidak berbahaya di sini.
            'file_name_original' => $file->getClientOriginalName(),
            'file_mime_type' => $mime,
            'file_size' => $file->getSize() ?: 0,
            'extraction_status' => $this->statusEkstraksiAwal($mime),
        ];
    }

    /**
     * Menghapus berkas yang terlanjur tersimpan.
     *
     * Dipanggil saat penyimpanan basis data gagal setelah berkasnya tertulis.
     * Tanpa ini, setiap kegagalan menyisakan berkas yang tidak dirujuk
     * baris mana pun — dan tidak ada yang menyadarinya sampai cakram penuh.
     */
    public function hapus(string $path): void
    {
        Storage::disk('local')->delete($path);
    }

    /**
     * Sharding per tahun/bulan sejak awal, supaya tidak ada satu folder datar
     * berisi ribuan berkas (`PRD.md` §8.4).
     */
    private function folder(): string
    {
        return 'documents/'.now()->format('Y/m');
    }

    /**
     * Nama berkas di disk di-UUID-kan (`PRD.md` §8.2).
     *
     * Nama asli tidak pernah dipakai: ia dapat memuat karakter yang bermakna
     * khusus di sistem berkas, dapat bertabrakan dengan berkas lain, dan dapat
     * membocorkan isi dokumen lewat nama berkas yang tertebak.
     */
    private function namaAcak(UploadedFile $file): string
    {
        $ekstensi = strtolower($file->getClientOriginalExtension());

        return $ekstensi === ''
            ? Str::uuid()->toString()
            : Str::uuid()->toString().'.'.$ekstensi;
    }

    /**
     * Status ekstraksi saat dokumen baru tersimpan (FR-32b).
     *
     * Tipe yang tidak didukung langsung ditandai `not_applicable` **tanpa job
     * pernah dibuat** — bukan job kosong yang selesai tanpa mengerjakan apa
     * pun. Antrian tidak perlu terisi pekerjaan yang sudah pasti tidak ada
     * hasilnya.
     */
    public function statusEkstraksiAwal(string $mime): ExtractionStatus
    {
        // HEIC dikecualikan lebih dulu meski awalannya cocok dengan `image/`:
        // Tesseract tidak mendukungnya, dan foto kamera iPhone bawaan berformat
        // ini (FR-32d).
        if (in_array($mime, config('dms.ekstraksi.mime_dikecualikan'), true)) {
            return ExtractionStatus::NotApplicable;
        }

        foreach (config('dms.ekstraksi.mime_didukung') as $awalan) {
            if (str_starts_with($mime, $awalan)) {
                return ExtractionStatus::Pending;
            }
        }

        return ExtractionStatus::NotApplicable;
    }
}
