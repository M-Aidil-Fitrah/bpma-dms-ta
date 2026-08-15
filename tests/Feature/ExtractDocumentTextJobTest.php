<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ExtractionStatus;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `ExtractDocumentTextJob` dijalankan langsung (bukan lewat antrian) atas
 * berkas contoh sungguhan, supaya percobaannya membuktikan pustaka pihak
 * ketiga benar-benar berhasil membaca formatnya (FR-32, FR-33).
 */
final class ExtractDocumentTextJobTest extends TestCase
{
    use RefreshDatabase;

    private function taruhBerkasContoh(string $namaBerkas, string $mime): Document
    {
        $tujuan = 'documents/2026/08/'.$namaBerkas;

        Storage::disk('local')->put(
            $tujuan,
            (string) file_get_contents(base_path('database/seeders/files/'.$namaBerkas)),
        );

        return Document::factory()->create([
            'file_path' => $tujuan,
            'file_mime_type' => $mime,
            'extraction_status' => ExtractionStatus::Pending,
            'extracted_text' => null,
        ]);
    }

    public function test_pdf_berteks_menjadi_completed(): void
    {
        $document = $this->taruhBerkasContoh('sop-pengendalian-dokumen.pdf', 'application/pdf');

        app()->call([new ExtractDocumentTextJob($document), 'handle']);

        $document->refresh();
        $this->assertSame(ExtractionStatus::Completed, $document->extraction_status);
        $this->assertNotNull($document->extracted_text);
        $this->assertNotSame('', $document->extracted_text);
    }

    public function test_pdf_hasil_pindaian_menjadi_failed(): void
    {
        // FR-32c pada FEAT-11 penuh minta `completed` teks kosong — itu
        // berlaku begitu OCR (FEAT-11b) ada sebagai jalan keluar. Selama
        // 11a berjalan sendirian, status ini dibuat `failed`
        // (Progres-dan-Lanjutan.md §7.3) supaya tidak diam-diam terlihat
        // "sudah dapat dicari" padahal isinya kosong.
        $document = $this->taruhBerkasContoh('nota-dinas-hasil-pindai.pdf', 'application/pdf');

        app()->call([new ExtractDocumentTextJob($document), 'handle']);

        $document->refresh();
        $this->assertSame(ExtractionStatus::Failed, $document->extraction_status);
        $this->assertNull($document->extracted_text);
    }

    public function test_docx_menjadi_completed(): void
    {
        $document = $this->taruhBerkasContoh(
            'notulen-rapat-koordinasi.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        app()->call([new ExtractDocumentTextJob($document), 'handle']);

        $document->refresh();
        $this->assertSame(ExtractionStatus::Completed, $document->extraction_status);
        $this->assertNotSame('', $document->extracted_text);
    }

    public function test_txt_menjadi_completed(): void
    {
        $document = $this->taruhBerkasContoh('daftar-inventaris-aset.txt', 'text/plain');

        app()->call([new ExtractDocumentTextJob($document), 'handle']);

        $document->refresh();
        $this->assertSame(ExtractionStatus::Completed, $document->extraction_status);
        $this->assertNotSame('', $document->extracted_text);
    }

    public function test_percobaan_dibatasi_dan_berhenti_bila_dokumen_hilang(): void
    {
        $document = $this->taruhBerkasContoh('sop-pengendalian-dokumen.pdf', 'application/pdf');
        $job = new ExtractDocumentTextJob($document);

        $this->assertSame(2, $job->tries);
        $this->assertTrue($job->deleteWhenMissingModels);
    }

    public function test_metode_failed_menandai_status_gagal(): void
    {
        $document = $this->taruhBerkasContoh('sop-pengendalian-dokumen.pdf', 'application/pdf');

        (new ExtractDocumentTextJob($document))->failed(new \RuntimeException('galat pengujian'));

        $document->refresh();
        $this->assertSame(ExtractionStatus::Failed, $document->extraction_status);
    }

    public function test_pdf_tidak_sah_berakhir_gagal(): void
    {
        // Baik `pdfparser` melempar pengecualian atas berkas yang sama
        // sekali bukan PDF, atau "berhasil" membaca tapi hasilnya kosong —
        // keduanya wajar berakhir `failed`, bukan `completed` diam-diam.
        // Blok try/catch di sini meniru persis yang dilakukan queue worker
        // sungguhan saat percobaan terakhir gagal.
        $tujuan = 'documents/2026/08/rusak.pdf';
        Storage::disk('local')->put($tujuan, 'ini bukan berkas pdf yang sah');

        $document = Document::factory()->create([
            'file_path' => $tujuan,
            'file_mime_type' => 'application/pdf',
            'extraction_status' => ExtractionStatus::Pending,
        ]);

        $job = new ExtractDocumentTextJob($document);

        try {
            app()->call([$job, 'handle']);
        } catch (\Throwable $e) {
            $job->failed($e);
        }

        $document->refresh();
        $this->assertSame(ExtractionStatus::Failed, $document->extraction_status);
    }
}
