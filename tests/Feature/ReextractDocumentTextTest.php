<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ExtractionStatus;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ReextractDocumentTextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_dokumen_gagal_dapat_dimasukkan_kembali_ke_antrean(): void
    {
        $document = Document::factory()->create([
            'file_mime_type' => 'image/jpeg',
            'extraction_status' => ExtractionStatus::Failed,
            'extracted_text' => 'hasil lama yang keliru',
        ]);

        $this->artisan('documents:reextract', ['documentId' => $document->id])
            ->expectsOutput("Dokumen {$document->id} dimasukkan ke antrean ekstraksi.")
            ->assertSuccessful();

        $document->refresh();
        $this->assertSame(ExtractionStatus::Pending, $document->extraction_status);
        $this->assertNull($document->extracted_text);
        Queue::assertPushed(ExtractDocumentTextJob::class);
    }

    public function test_tipe_tidak_didukung_tidak_dimasukkan_ke_antrean(): void
    {
        $document = Document::factory()->create([
            'file_mime_type' => 'video/mp4',
            'extraction_status' => ExtractionStatus::Failed,
        ]);

        $this->artisan('documents:reextract', ['documentId' => $document->id])
            ->expectsOutput("Dokumen {$document->id} tidak mendukung ekstraksi teks.")
            ->assertFailed();

        $document->refresh();
        $this->assertSame(ExtractionStatus::Failed, $document->extraction_status);
        Queue::assertNotPushed(ExtractDocumentTextJob::class);
    }

    public function test_tanpa_id_memproses_seluruh_dokumen_gagal_yang_didukung(): void
    {
        $pdf = Document::factory()->create([
            'file_mime_type' => 'application/pdf',
            'extraction_status' => ExtractionStatus::Failed,
        ]);
        $video = Document::factory()->create([
            'file_mime_type' => 'video/mp4',
            'extraction_status' => ExtractionStatus::Failed,
        ]);

        $this->artisan('documents:reextract')
            ->expectsOutput('1 dokumen dimasukkan kembali ke antrean ekstraksi.')
            ->assertSuccessful();

        $pdf->refresh();
        $video->refresh();
        $this->assertSame(ExtractionStatus::Pending, $pdf->extraction_status);
        $this->assertSame(ExtractionStatus::Failed, $video->extraction_status);
        Queue::assertPushed(ExtractDocumentTextJob::class, 1);
    }
}
