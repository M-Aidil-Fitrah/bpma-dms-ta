<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentThumbnailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turunan visual tidak pernah memengaruhi berkas asli, pencarian, atau status
 * dokumen. Karena itu kegagalannya cukup dicatat dan tidak mengganggu pengguna.
 */
final class GenerateDocumentThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Document $document) {}

    public function handle(DocumentThumbnailService $thumbnail): void
    {
        try {
            $thumbnail->generate($this->document);
        } catch (Throwable $e) {
            Log::warning('Gambar mini dokumen tidak dapat dibuat.', [
                'document_id' => $this->document->id,
                'mime' => $this->document->file_mime_type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
