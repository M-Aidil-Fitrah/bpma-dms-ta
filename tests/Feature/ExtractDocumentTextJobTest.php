<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ExtractionStatus;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RequiresBinaries;
use Tests\TestCase;

/**
 * `ExtractDocumentTextJob` dijalankan langsung (bukan lewat antrian) atas
 * berkas contoh sungguhan, supaya percobaannya membuktikan pustaka pihak
 * ketiga benar-benar berhasil membaca formatnya (FR-32, FR-33).
 */
final class ExtractDocumentTextJobTest extends TestCase
{
    use RefreshDatabase;
    use RequiresBinaries;

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

    public function test_pdf_hasil_pindaian_menjadi_completed_dengan_teks_ocr_dan_progres_halaman(): void
    {
        $this->requireBinary('gs');
        $this->requireTesseractLanguages(...explode('+', config('dms.ekstraksi.bahasa_ocr')));

        // PDF ini sengaja tidak punya text layer. Ia harus diraster halaman
        // demi halaman lalu dibaca Tesseract, bukan dianggap selesai kosong.
        $document = $this->taruhBerkasContoh('nota-dinas-hasil-pindai.pdf', 'application/pdf');

        app()->call([new ExtractDocumentTextJob($document), 'handle']);

        $document->refresh();
        $this->assertSame(ExtractionStatus::Completed, $document->extraction_status);
        $this->assertNotNull($document->extracted_text);
        $this->assertStringContainsString('Anggaran', $document->extracted_text);
        $this->assertSame(2, $document->extraction_pages_total);
        $this->assertSame(2, $document->extraction_pages_processed);
        $this->assertNull($document->extraction_estimated_seconds);
        $this->assertNotNull($document->extraction_started_at);
        $this->assertNull($document->extraction_message);
    }

    public function test_pdf_pindaian_melebihi_batas_halaman_berakhir_gagal_tanpa_dicoba_ulang(): void
    {
        $document = $this->taruhBerkasContoh('nota-dinas-hasil-pindai.pdf', 'application/pdf');
        $batasAsli = config('dms.ekstraksi.pdf_ocr_maks_halaman');
        config(['dms.ekstraksi.pdf_ocr_maks_halaman' => 0]);

        try {
            app()->call([new ExtractDocumentTextJob($document), 'handle']);
        } finally {
            config(['dms.ekstraksi.pdf_ocr_maks_halaman' => $batasAsli]);
        }

        $document->refresh();
        $this->assertSame(50, $batasAsli);
        $this->assertSame(ExtractionStatus::Failed, $document->extraction_status);
        $this->assertSame(2, $document->extraction_pages_total);
        $this->assertSame(0, $document->extraction_pages_processed);
        $this->assertSame('PDF pindaian melebihi batas OCR 0 halaman.', $document->extraction_message);
    }

    public function test_gambar_bernaskah_menjadi_completed_dengan_teks_ocr(): void
    {
        $this->requireTesseractLanguages(...explode('+', config('dms.ekstraksi.bahasa_ocr')));

        $document = $this->taruhBerkasContoh('nota-dinas-foto.jpg', 'image/jpeg');

        app()->call([new ExtractDocumentTextJob($document), 'handle']);

        $document->refresh();
        $this->assertSame(ExtractionStatus::Completed, $document->extraction_status);
        $this->assertNotNull($document->extracted_text);
        $this->assertStringContainsString('Rekonsiliasi Data Penerimaan', $document->extracted_text);
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
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('extract-document-'.$document->id, $job->uniqueId());
        $this->assertSame(1800, $job->uniqueFor);
        $this->assertSame(config('dms.ekstraksi.pdf_ocr_timeout_detik'), $job->timeout);
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

    public function test_gambar_rusak_berakhir_gagal_bukan_menunggu_selamanya(): void
    {
        $tujuan = 'documents/2026/08/rusak.jpg';
        Storage::disk('local')->put($tujuan, 'ini bukan gambar jpeg yang sah');

        $document = Document::factory()->create([
            'file_path' => $tujuan,
            'file_mime_type' => 'image/jpeg',
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
        $this->assertNull($document->extracted_text);
    }
}
