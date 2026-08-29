<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Menjaga test integrasi perkakas sistem tetap jujur di mesin pengembang.
 *
 * Test yang benar-benar menjalankan OCR, Ghostscript, atau LibreOffice harus
 * dilewati bila dependensi sistemnya tidak tersedia. Test unit/failure mode
 * yang memakai proses palsu tetap dijalankan terpisah.
 */
trait RequiresBinaries
{
    protected function requireBinary(string $binary): void
    {
        if ((new ExecutableFinder)->find($binary) === null) {
            $this->markTestSkipped("Binary sistem {$binary} tidak tersedia.");
        }
    }

    protected function requireTesseractLanguages(string ...$languages): void
    {
        $this->requireBinary('tesseract');

        $process = new Process(['tesseract', '--list-langs']);
        $process->run();

        $available = preg_split('/\R/', $process->getOutput()) ?: [];
        $missing = array_diff($languages, array_map('trim', $available));

        if ($process->isSuccessful() && $missing === []) {
            return;
        }

        $this->markTestSkipped('Data bahasa Tesseract tidak tersedia: '.implode(', ', $missing ?: $languages));
    }
}
