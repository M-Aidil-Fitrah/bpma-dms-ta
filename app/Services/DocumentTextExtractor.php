<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\OcrResult;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;
use thiagoalessio\TesseractOCR\TesseractOCR;
use UnexpectedValueException;

/**
 * Membaca isi teks berkas untuk keperluan pencarian (FR-32, FR-33).
 *
 * Dipakai `ExtractDocumentTextJob` (unggahan sungguhan) DAN
 * `Database\Seeders\Support\BerkasContoh` (data seed). Keduanya wajib
 * memakai cara baca yang identik — kalau berbeda, teks hasil seed dan hasil
 * unggahan sungguhan bisa diam-diam tidak konsisten.
 */
final class DocumentTextExtractor
{
    public function pdf(string $path): string
    {
        if (file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            throw new UnexpectedValueException('Berkas tidak memiliki header PDF yang sah.');
        }

        return trim((new Parser)->parseFile($path)->getText());
    }

    /**
     * Membaca teks dan jumlah halaman dalam SATU parse — dipakai jalur OCR
     * PDF pindaian, yang sebelumnya memparse penuh berkas yang sama dua kali
     * berurutan (`pdf()` untuk cek teks digital, lalu parse kedua khusus
     * menghitung halaman sebelum limit OCR diterapkan). PDF pindaian besar
     * mahal untuk diparse; ini memangkas biayanya jadi separuh.
     *
     * @return array{teks: string, halaman: int}
     */
    public function pdfTeksDanHalaman(string $path): array
    {
        if (file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            throw new UnexpectedValueException('Berkas tidak memiliki header PDF yang sah.');
        }

        $dokumen = (new Parser)->parseFile($path);
        $halaman = count($dokumen->getPages());

        if ($halaman < 1) {
            throw new UnexpectedValueException('PDF tidak memiliki halaman yang dapat dipindai.');
        }

        return ['teks' => trim($dokumen->getText()), 'halaman' => $halaman];
    }

    public function docx(string $path): string
    {
        $teks = '';

        foreach (IOFactory::load($path)->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText') && is_string($element->getText())) {
                    $teks .= $element->getText().' ';
                }
            }
        }

        return trim($teks);
    }

    public function txt(string $path): string
    {
        $maksBytes = (int) config('dms.ekstraksi.txt_maks_bytes');
        $isi = (string) file_get_contents($path, false, null, 0, $maksBytes);

        return trim($this->pastikanUtf8($isi));
    }

    /**
     * `extracted_text` disimpan dan dikirim sebagai JSON — byte yang bukan
     * UTF-8 sah (umum pada berkas TXT lama berencoding Windows-1252/Latin-1)
     * akan merusak seluruh respons halaman detail dokumen tersebut kalau
     * dibiarkan apa adanya.
     */
    private function pastikanUtf8(string $isi): string
    {
        // Pemotongan di `txt()` bisa berhenti persis di tengah karakter
        // UTF-8 multi-byte. Coba buang beberapa byte terakhir dulu sebelum
        // menyimpulkan berkasnya memang berencoding lain — mencegah berkas
        // UTF-8 sah dikonversi keliru hanya karena terpotong di batas byte.
        for ($potong = 0; $potong <= 3 && $potong < strlen($isi); $potong++) {
            $kandidat = $potong === 0 ? $isi : substr($isi, 0, -$potong);

            if (mb_check_encoding($kandidat, 'UTF-8')) {
                return $kandidat;
            }
        }

        $terdeteksi = mb_detect_encoding($isi, ['Windows-1252', 'ISO-8859-1'], true);

        return mb_convert_encoding($isi, 'UTF-8', $terdeteksi ?: 'Windows-1252');
    }

    /**
     * Membaca teks dalam gambar langsung. HEIC tidak pernah mencapai metode
     * ini karena ditandai `not_applicable` sebelum job dibuat.
     */
    public function gambar(string $path): OcrResult
    {
        $tsv = (new TesseractOCR($path))
            ->lang(...explode('+', config('dms.ekstraksi.bahasa_ocr')))
            ->tsv()
            ->run((int) config('dms.ekstraksi.ocr_timeout_detik'));

        $kata = [];
        $confidence = [];
        foreach (preg_split('/\R/', $tsv) ?: [] as $baris) {
            $kolom = explode("\t", $baris);
            if (count($kolom) < 12 || $kolom[0] !== '5' || trim($kolom[11]) === '') {
                continue;
            }
            $kata[] = trim($kolom[11]);
            if (is_numeric($kolom[10]) && (float) $kolom[10] >= 0) {
                $confidence[] = (float) $kolom[10];
            }
        }

        return new OcrResult(trim(implode(' ', $kata)), $confidence);
    }
}
