<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Services\DocumentTextExtractor;
use App\Services\ScannedPdfOcr;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

/**
 * Mengisi `extracted_text` di latar belakang supaya dokumen dapat ditemukan
 * lewat pencarian isi (FR-32, FR-33).
 *
 * PDF, DOCX, TXT, dan gambar langsung diproses melalui satu jalur supaya
 * hasil unggahan nyata dan seed tidak dapat menyimpang diam-diam.
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

    /** Batas total OCR PDF pindaian; konfigurasi membatasi tiap halaman. */
    public int $timeout;

    /**
     * Dokumennya bisa saja sudah tidak ada saat job akhirnya dijalankan.
     * Tanpa ini, job gagal dengan `ModelNotFoundException` dan menumpuk di
     * `failed_jobs` untuk sesuatu yang bukan kegagalan sesungguhnya.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Document $document)
    {
        $this->timeout = (int) config('dms.ekstraksi.pdf_ocr_timeout_detik');
    }

    public function handle(DocumentTextExtractor $ekstraktor, ScannedPdfOcr $pdfOcr): void
    {
        $path = Storage::disk('local')->path($this->document->file_path);
        $mime = $this->document->file_mime_type;
        $mulai = now();

        $this->document->update([
            'extraction_pages_total' => null,
            'extraction_pages_processed' => null,
            'extraction_estimated_seconds' => null,
            'extraction_message' => 'Membaca isi dokumen di latar belakang.',
            'extraction_started_at' => $mulai,
        ]);

        $teks = match (true) {
            $mime === 'application/pdf' => $this->ekstrakPdf($path, $ekstraktor, $pdfOcr, $mulai),
            $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => $ekstraktor->docx($path),
            $mime === 'text/plain' => $ekstraktor->txt($path),
            str_starts_with($mime, 'image/') => $ekstraktor->gambar($path),
            default => throw new LogicException("ExtractDocumentTextJob belum menangani tipe {$mime}."),
        };

        // PDF melebihi 50 halaman diberi kegagalan yang dapat dipahami
        // pengguna, tanpa melemparkannya lagi ke percobaan queue berikutnya.
        if ($teks === null) {
            return;
        }

        $this->document->update([
            // PDF pindaian maupun foto tanpa naskah berhasil dibaca dengan
            // hasil kosong. Itu `completed`, bukan kegagalan ekstraksi.
            'extracted_text' => $teks === '' ? null : $teks,
            'extraction_status' => ExtractionStatus::Completed,
            'extraction_estimated_seconds' => null,
            'extraction_message' => null,
        ]);
    }

    private function ekstrakPdf(
        string $path,
        DocumentTextExtractor $ekstraktor,
        ScannedPdfOcr $pdfOcr,
        CarbonInterface $mulai,
    ): ?string {
        ['teks' => $teksDigital, 'halaman' => $jumlahHalaman] = $ekstraktor->pdfTeksDanHalaman($path);

        if ($teksDigital !== '') {
            return $teksDigital;
        }

        $batasHalaman = (int) config('dms.ekstraksi.pdf_ocr_maks_halaman');

        if ($jumlahHalaman > $batasHalaman) {
            $this->document->update([
                'extraction_status' => ExtractionStatus::Failed,
                'extraction_pages_total' => $jumlahHalaman,
                'extraction_pages_processed' => 0,
                'extraction_estimated_seconds' => null,
                'extraction_message' => "PDF pindaian melebihi batas OCR {$batasHalaman} halaman.",
            ]);

            Log::warning('OCR PDF pindaian melewati batas halaman.', [
                'document_id' => $this->document->id,
                'pages' => $jumlahHalaman,
                'limit' => $batasHalaman,
            ]);

            return null;
        }

        $this->document->update([
            'extraction_pages_total' => $jumlahHalaman,
            'extraction_pages_processed' => 0,
            'extraction_message' => "Menyiapkan OCR untuk {$jumlahHalaman} halaman.",
        ]);

        return $pdfOcr->extract($path, $jumlahHalaman, function (int $selesai, int $total) use ($mulai): void {
            $berlalu = max(1, $mulai->diffInSeconds(now()));
            $sisa = $total - $selesai;
            $estimasi = $sisa === 0 ? null : (int) ceil(($berlalu / $selesai) * $sisa);

            $this->document->update([
                'extraction_pages_processed' => $selesai,
                'extraction_estimated_seconds' => $estimasi,
                'extraction_message' => "OCR halaman {$selesai} dari {$total}.",
            ]);
        });
    }

    /**
     * Dipanggil framework setelah seluruh percobaan ($tries) habis.
     * Tanpa ini kegagalan permanen membuat status macet di `pending`
     * selamanya (FR-33).
     */
    public function failed(?Throwable $exception): void
    {
        $this->document->update([
            'extraction_status' => ExtractionStatus::Failed,
            'extraction_estimated_seconds' => null,
            'extraction_message' => 'Ekstraksi tidak selesai. Berkas asli tetap dapat diunduh.',
        ]);

        Log::warning('Ekstraksi teks gagal permanen.', [
            'document_id' => $this->document->id,
            'mime' => $this->document->file_mime_type,
            'error' => $exception?->getMessage(),
        ]);
    }
}
