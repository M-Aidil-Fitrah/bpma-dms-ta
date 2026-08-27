<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PreviewStatus;
use App\Jobs\GenerateDocumentThumbnailJob;
use App\Models\Document;
use App\Services\DocumentThumbnailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Memakai perkakas sistem yang sama dengan aplikasi, bukan proses palsu.
 * Gambar mini tidak berguna bila LibreOffice/Ghostscript hanya "teruji" lewat
 * mock tetapi gagal ketika benar-benar diberi berkas dokumen.
 */
final class DocumentThumbnailServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentThumbnailService $thumbnail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->thumbnail = new DocumentThumbnailService;
    }

    private function dokumenDenganBerkas(string $nama, string $mime): Document
    {
        $path = 'documents/2026/08/'.$nama;

        Storage::disk('local')->put(
            $path,
            (string) file_get_contents(base_path('database/seeders/files/'.$nama)),
        );

        return Document::factory()->create([
            'file_path' => $path,
            'file_name_original' => $nama,
            'file_mime_type' => $mime,
        ]);
    }

    public function test_gambar_langsung_menghasilkan_thumbnail_jpeg(): void
    {
        $document = $this->dokumenDenganBerkas('nota-dinas-foto.jpg', 'image/jpeg');

        $this->thumbnail->generate($document);

        $document->refresh();
        $this->assertNotNull($document->thumbnail_path);
        $this->assertNull($document->preview_path);
        Storage::disk('local')->assertExists($document->thumbnail_path);
        $this->assertSame('image/jpeg', mime_content_type(Storage::disk('local')->path($document->thumbnail_path)));
    }

    public function test_pdf_menghasilkan_thumbnail_halaman_pertama(): void
    {
        $document = $this->dokumenDenganBerkas('sop-pengendalian-dokumen.pdf', 'application/pdf');

        $this->thumbnail->generate($document);

        $document->refresh();
        $this->assertNotNull($document->thumbnail_path);
        $this->assertNull($document->preview_path);
        Storage::disk('local')->assertExists($document->thumbnail_path);
    }

    public function test_docx_menghasilkan_pdf_pratinjau_dan_thumbnail(): void
    {
        $document = $this->dokumenDenganBerkas(
            'notulen-rapat-koordinasi.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $this->thumbnail->generate($document);

        $document->refresh();
        $this->assertNotNull($document->thumbnail_path);
        $this->assertNotNull($document->preview_path);
        $this->assertSame(PreviewStatus::Ready, $document->preview_status);
        Storage::disk('local')->assertExists($document->thumbnail_path);
        Storage::disk('local')->assertExists($document->preview_path);
        $this->assertSame('%PDF-', file_get_contents(Storage::disk('local')->path($document->preview_path), false, null, 0, 5));
    }

    public function test_tipe_tanpa_wujud_visual_tidak_membuat_turunan(): void
    {
        $document = $this->dokumenDenganBerkas('arsip-lampiran-pendukung.zip', 'application/zip');

        $this->thumbnail->generate($document);

        $document->refresh();
        $this->assertNull($document->thumbnail_path);
        $this->assertNull($document->preview_path);
    }

    public function test_setiap_jenis_office_memakai_filter_pdf_aplikasi_yang_tepat(): void
    {
        $this->assertSame('writer_pdf_Export', $this->thumbnail->filterOffice('application/msword'));
        $this->assertSame('writer_pdf_Export', $this->thumbnail->filterOffice('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        $this->assertSame('calc_pdf_Export', $this->thumbnail->filterOffice('application/vnd.ms-excel'));
        $this->assertSame('calc_pdf_Export', $this->thumbnail->filterOffice('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
        $this->assertSame('impress_pdf_Export', $this->thumbnail->filterOffice('application/vnd.ms-powerpoint'));
        $this->assertSame('impress_pdf_Export', $this->thumbnail->filterOffice('application/vnd.openxmlformats-officedocument.presentationml.presentation'));
    }

    public function test_job_thumbnail_gagal_tetap_membiarkan_dokumen_dapat_dipakai(): void
    {
        $path = 'documents/2026/08/rusak.pdf';
        Storage::disk('local')->put($path, 'ini bukan PDF yang dapat dirender');
        $document = Document::factory()->create([
            'file_path' => $path,
            'file_mime_type' => 'application/pdf',
        ]);

        (new GenerateDocumentThumbnailJob($document))->handle($this->thumbnail);

        $document->refresh();
        $this->assertNull($document->thumbnail_path);
        $this->assertNull($document->preview_path);
    }

    public function test_job_office_gagal_menandai_pratinjau_gagal_tanpa_mengubah_berkas_asli(): void
    {
        $path = 'documents/2026/08/rusak.docx';
        $isiAsli = 'isi dokumen asli';
        Storage::disk('local')->put($path, $isiAsli);
        $document = Document::factory()->create([
            'file_path' => $path,
            'file_name_original' => 'rusak.docx',
            'file_mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'preview_status' => PreviewStatus::Processing,
        ]);
        Process::fake([
            '*' => Process::result(errorOutput: 'LibreOffice tidak dapat membaca dokumen.', exitCode: 1),
        ]);

        (new GenerateDocumentThumbnailJob($document))->handle($this->thumbnail);

        $document->refresh();
        $this->assertSame(PreviewStatus::Failed, $document->preview_status);
        $this->assertNotNull($document->preview_message);
        $this->assertNull($document->preview_path);
        $this->assertSame($isiAsli, Storage::disk('local')->get($path));
    }
}
