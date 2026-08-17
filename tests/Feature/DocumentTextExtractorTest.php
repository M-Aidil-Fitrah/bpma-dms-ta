<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DocumentTextExtractor;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use UnexpectedValueException;

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
        $teks = $this->ekstraktor->gambar(base_path('database/seeders/files/nota-dinas-foto.jpg'))->text;

        $this->assertNotSame('', $teks);
    }

    public function test_gambar_byte_rusak_ditolak_sebelum_dikirim_ke_tesseract(): void
    {
        $path = Storage::disk('local')->path('gambar-rusak.jpg');
        file_put_contents($path, 'bukan gambar JPEG yang sah');

        $this->expectException(UnexpectedValueException::class);
        $this->ekstraktor->gambar($path);
    }

    public function test_txt_berencoding_windows_1252_dikonversi_ke_utf8(): void
    {
        $path = Storage::disk('local')->path('test-encoding.txt');
        // 0xE9 adalah 'é' di Windows-1252, tapi byte tidak sah sebagai UTF-8
        // berdiri sendiri — tanpa konversi, ini merusak JSON respons halaman.
        file_put_contents($path, "Rapat tim kualit\xE9 pengendalian dokumen.");

        $teks = $this->ekstraktor->txt($path);

        $this->assertTrue(mb_check_encoding($teks, 'UTF-8'));
        $this->assertStringContainsString('kualité', $teks);
    }

    public function test_txt_utf8_multibyte_tidak_rusak_oleh_deteksi_encoding(): void
    {
        $path = Storage::disk('local')->path('test-utf8.txt');
        file_put_contents($path, 'Café résumé — sudah UTF-8 sah, jangan diutak-atik.');

        $teks = $this->ekstraktor->txt($path);

        $this->assertSame('Café résumé — sudah UTF-8 sah, jangan diutak-atik.', $teks);
    }

    public function test_txt_dibatasi_ukuran_maksimum(): void
    {
        $path = Storage::disk('local')->path('test-besar.txt');
        $batas = (int) config('dms.ekstraksi.txt_maks_bytes');
        file_put_contents($path, str_repeat('A', $batas + (1024 * 1024)));

        $teks = $this->ekstraktor->txt($path);

        $this->assertLessThanOrEqual($batas, strlen($teks));
    }
}
