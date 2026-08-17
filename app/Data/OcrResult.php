<?php

declare(strict_types=1);

namespace App\Data;

/** Hasil OCR yang membawa teks sekaligus bukti kualitasnya. */
final readonly class OcrResult
{
    /** @param list<float> $confidence */
    public function __construct(public string $text, public array $confidence) {}

    public function isLayak(): bool
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $this->text, $kata);
        $karakter = preg_replace('/[^\p{L}\p{N}]/u', '', $this->text) ?? '';
        $rataRata = $this->confidence === [] ? 0 : array_sum($this->confidence) / count($this->confidence);

        return count($kata[0]) >= 2 && mb_strlen($karakter) >= 8 && $rataRata >= 60;
    }
}
