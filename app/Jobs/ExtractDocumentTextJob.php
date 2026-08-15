<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Services\DocumentTextExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

/**
 * Mengisi `extracted_text` di latar belakang supaya dokumen dapat ditemukan
 * lewat pencarian isi (FR-32, FR-33).
 *
 * Hanya PDF, DOCX, dan TXT — tipe yang tidak butuh perkakas tingkat sistem
 * operasi. Gambar (OCR Tesseract) sengaja ditunda ke FEAT-11b; lihat
 * `config('dms.ekstraksi.mime_didukung')`.
 */
final class ExtractDocumentTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Percobaan pertama yang gagal karena sebab sementara (mis. berkas
     * belum tuntas ditulis ke disk jaringan) mendapat satu kesempatan lagi
     * sebelum ditandai gagal permanen.
     */
    public int $tries = 2;

    /**
     * Dokumennya bisa saja sudah tidak ada saat job akhirnya dijalankan.
     * Tanpa ini, job gagal dengan `ModelNotFoundException` dan menumpuk di
     * `failed_jobs` untuk sesuatu yang bukan kegagalan sesungguhnya.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Document $document) {}

    public function handle(DocumentTextExtractor $ekstraktor): void
    {
        $path = Storage::disk('local')->path($this->document->file_path);
        $mime = $this->document->file_mime_type;

        $teks = match ($mime) {
            'application/pdf' => $ekstraktor->pdf($path),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => $ekstraktor->docx($path),
            'text/plain' => $ekstraktor->txt($path),
            default => throw new LogicException("ExtractDocumentTextJob belum menangani tipe {$mime}."),
        };

        if ($teks === '' && $mime === 'application/pdf') {
            // PDF hasil pindaian tanpa lapisan teks — bukan galat,
            // `pdfparser` berhasil membaca berkasnya, memang tidak ada teks
            // untuk diambil. Lihat keputusan di §7.3 dokumen progres.
            $this->document->update(['extraction_status' => ExtractionStatus::Failed]);

            return;
        }

        $this->document->update([
            'extracted_text' => $teks,
            'extraction_status' => ExtractionStatus::Completed,
        ]);
    }

    /**
     * Dipanggil framework setelah seluruh percobaan ($tries) habis.
     * Tanpa ini kegagalan permanen membuat status macet di `pending`
     * selamanya (FR-33).
     */
    public function failed(?Throwable $exception): void
    {
        $this->document->update(['extraction_status' => ExtractionStatus::Failed]);
    }
}
