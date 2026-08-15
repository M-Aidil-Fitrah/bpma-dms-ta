<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Satu potongan pada diagram komposisi kategori di dasbor (FR-02).
 */
#[TypeScript]
final class KategoriRingkasData extends Data
{
    public function __construct(
        public int $id,
        public string $nama,
        public int $jumlah,
    ) {}
}
