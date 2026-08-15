<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

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
        return trim((new Parser)->parseFile($path)->getText());
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
        return trim((string) file_get_contents($path));
    }
}
