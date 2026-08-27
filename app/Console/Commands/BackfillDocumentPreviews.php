<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PreviewStatus;
use App\Jobs\GenerateDocumentThumbnailJob;
use App\Models\Document;
use App\Services\DocumentThumbnailService;
use Illuminate\Console\Command;

/**
 * Mengantrikan pembuatan PDF pratinjau untuk arsip Office yang sudah ada.
 *
 * Command ini tidak mengonversi di proses CLI dan tidak mengubah berkas asli;
 * pekerjaan berat selalu tetap berada di antrean `thumbnail`.
 */
final class BackfillDocumentPreviews extends Command
{
    protected $signature = 'documents:backfill-previews
                            {--chunk=50 : Jumlah dokumen yang diambil per chunk}
                            {--retry-failed : Coba ulang dokumen Office yang pratinjau sebelumnya gagal}';

    protected $description = 'Mengantrikan pratinjau PDF untuk dokumen Office lama yang belum diproses';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $status = $this->option('retry-failed')
            ? [PreviewStatus::NotApplicable->value, PreviewStatus::Failed->value]
            : [PreviewStatus::NotApplicable->value];
        $queued = 0;

        Document::query()
            ->whereIn('file_mime_type', DocumentThumbnailService::MIME_OFFICE)
            ->whereNull('preview_path')
            ->whereIn('preview_status', $status)
            ->orderBy('id')
            ->chunkById($chunk, function ($documents) use (&$queued): void {
                foreach ($documents as $document) {
                    $document->forceFill([
                        'preview_status' => PreviewStatus::Processing,
                        'preview_message' => null,
                    ])->save();

                    GenerateDocumentThumbnailJob::dispatch($document)->onQueue('thumbnail');
                    $queued++;
                }
            });

        $this->components->info("{$queued} dokumen Office dimasukkan ke antrean thumbnail.");

        return self::SUCCESS;
    }
}
