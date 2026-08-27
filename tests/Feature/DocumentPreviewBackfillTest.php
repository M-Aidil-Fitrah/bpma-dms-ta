<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PreviewStatus;
use App\Jobs\GenerateDocumentThumbnailJob;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class DocumentPreviewBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_hanya_mengantrikan_office_lama_tanpa_pratinjau_dan_aman_dijalankan_ulang(): void
    {
        $office = Document::factory()->create([
            'file_name_original' => 'notulen.docx',
            'file_mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
        Document::factory()->create(['file_mime_type' => 'application/pdf']);
        Document::factory()->create([
            'file_name_original' => 'sudah-jadi.docx',
            'file_mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'preview_path' => 'previews/2026/08/sudah-jadi.pdf',
        ]);

        Queue::fake();
        $this->artisan('documents:backfill-previews')->assertExitCode(0);

        $office->refresh();
        $this->assertSame(PreviewStatus::Processing, $office->preview_status);
        Queue::assertPushed(GenerateDocumentThumbnailJob::class, fn (GenerateDocumentThumbnailJob $job): bool => $job->document->is($office));

        Queue::fake();
        $this->artisan('documents:backfill-previews')->assertExitCode(0);
        Queue::assertNothingPushed();
    }

    public function test_backfill_hanya_mencoba_ulang_kegagalan_bila_diminta(): void
    {
        $office = Document::factory()->create([
            'file_name_original' => 'gagal.docx',
            'file_mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'preview_status' => PreviewStatus::Failed,
        ]);

        Queue::fake();
        $this->artisan('documents:backfill-previews')->assertExitCode(0);
        Queue::assertNothingPushed();

        $this->artisan('documents:backfill-previews', ['--retry-failed' => true])->assertExitCode(0);
        $office->refresh();
        $this->assertSame(PreviewStatus::Processing, $office->preview_status);
        Queue::assertPushed(GenerateDocumentThumbnailJob::class, fn (GenerateDocumentThumbnailJob $job): bool => $job->document->is($office));
    }
}
