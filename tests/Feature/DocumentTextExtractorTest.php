<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DocumentTextExtractor;
use Tests\TestCase;

/**
 * Pembacaan teks mentah dari tipe berkas yang didukung FEAT-11.
 *
 * Dijalankan atas berkas contoh sungguhan di `database/seeders/files/` —
 * bukan berkas tiruan — supaya benar-benar membuktikan pustaka pihak ketiga
 * (`smalot/pdfparser`, `phpoffice/phpword`) dapat membaca format aslinya.
 */
final class DocumentTextExtractorTest extends TestCase
{
    private DocumentTextExtractor $ekstraktor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ekstraktor = new DocumentTextExtractor;
    }

    public function test_pdf_berteks_menghasilkan_isi(): void
    {
        $teks = $this->ekstraktor->pdf(base_path('database/seeders/files/sop-pengendalian-dokumen.pdf'));

        $this->assertNotSame('', $teks);
    }

    public function test_pdf_hasil_pindaian_menghasilkan_teks_kosong(): void
    {
        $teks = $this->ekstraktor->pdf(base_path('database/seeders/files/nota-dinas-hasil-pindai.pdf'));

        $this->assertSame('', $teks);
    }

    public function test_docx_menghasilkan_isi(): void
    {
        $teks = $this->ekstraktor->docx(base_path('database/seeders/files/notulen-rapat-koordinasi.docx'));

        $this->assertNotSame('', $teks);
    }

    public function test_txt_menghasilkan_isi(): void
    {
        $teks = $this->ekstraktor->txt(base_path('database/seeders/files/daftar-inventaris-aset.txt'));

        $this->assertNotSame('', $teks);
    }

    public function test_gambar_bernaskah_menghasilkan_teks_ocr(): void
    {
        $teks = $this->ekstraktor->gambar(base_path('database/seeders/files/nota-dinas-foto.jpg'));

        $this->assertNotSame('', $teks);
    }
}
