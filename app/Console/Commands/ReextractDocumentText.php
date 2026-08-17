<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ExtractionStatus;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use App\Services\DocumentUploadService;
use Illuminate\Console\Command;

/** Mencoba ulang ekstraksi yang sebelumnya gagal setelah lingkungan diperbaiki. */
final class ReextractDocumentText extends Command
{
    protected $signature = 'documents:reextract {documentId? : ID dokumen; tanpa nilai memproses seluruh yang gagal}';

    protected $description = 'Antrekan ulang ekstraksi teks dokumen yang gagal.';

    public function handle(DocumentUploadService $uploader): int
    {
        $id = $this->argument('documentId');

        if ($id !== null) {
            $document = Document::find($id);

            if ($document === null) {
                $this->error("Dokumen {$id} tidak ditemukan.");

                return self::FAILURE;
            }

            return $this->antrekan($document, $uploader) ? self::SUCCESS : self::FAILURE;
        }

        $jumlah = 0;

        Document::query()
            ->where('extraction_status', ExtractionStatus::Failed)
            ->each(function (Document $document) use ($uploader, &$jumlah): void {
                if ($this->antrekan($document, $uploader, false)) {
                    $jumlah++;
                }
            });

        $this->info("{$jumlah} dokumen dimasukkan kembali ke antrean ekstraksi.");

        return self::SUCCESS;
    }

    private function antrekan(Document $document, DocumentUploadService $uploader, bool $tampilkanPesan = true): bool
    {
        if ($uploader->statusEkstraksiAwal($document->file_mime_type) !== ExtractionStatus::Pending) {
            if ($tampilkanPesan) {
                $this->warn("Dokumen {$document->id} tidak mendukung ekstraksi teks.");
            }

            return false;
        }

        // Mencegah dua job OCR berjalan bersamaan untuk dokumen yang sama —
        // hanya dokumen yang benar-benar gagal yang boleh diproses ulang,
        // bukan yang masih `pending` (job lama sedang jalan) atau `completed`.
        if ($document->extraction_status !== ExtractionStatus::Failed) {
            if ($tampilkanPesan) {
                $this->warn("Dokumen {$document->id} berstatus {$document->extraction_status->value}, bukan gagal — dilewati.");
            }

            return false;
        }

        $document->update([
            'extracted_text' => null,
            'extraction_status' => ExtractionStatus::Pending,
            'extraction_pages_total' => null,
            'extraction_pages_processed' => null,
            'extraction_estimated_seconds' => null,
            'extraction_message' => null,
            'extraction_started_at' => null,
        ]);
        ExtractDocumentTextJob::dispatch($document);

        if ($tampilkanPesan) {
            $this->info("Dokumen {$document->id} dimasukkan ke antrean ekstraksi.");
        }

        return true;
    }
}
