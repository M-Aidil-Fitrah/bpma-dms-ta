<?php

declare(strict_types=1);

namespace App\Data;

use App\Services\PengaturanService;
use App\Support\BatasUnggah;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Nilai Pengaturan yang aman dikirim ke formulir Superadmin. */
#[TypeScript]
final class PengaturanFormData extends Data
{
    public function __construct(
        public int $unggah_batas_kb,
        public int $unggah_batas_kb_bawaan,
        public int $unggah_batas_tertinggi_kb,
        public ?int $unggah_batas_efektif_kb,
        public bool $unggah_dibatasi_php,
        public int $dokumen_per_halaman,
        public int $dokumen_per_halaman_bawaan,
    ) {}

    public static function dariService(PengaturanService $pengaturan): self
    {
        return new self(
            unggah_batas_kb: (int) $pengaturan->integer('unggah.batas_kb'),
            unggah_batas_kb_bawaan: (int) config('dms.dokumen.ukuran_maksimum_kb'),
            unggah_batas_tertinggi_kb: (int) config('dms.dokumen.ukuran_tertinggi_kb'),
            unggah_batas_efektif_kb: BatasUnggah::kilobyte(),
            unggah_dibatasi_php: BatasUnggah::dibatasiPhp(),
            dokumen_per_halaman: (int) $pengaturan->integer('dokumen.per_halaman'),
            dokumen_per_halaman_bawaan: (int) config('dms.dokumen.per_halaman'),
        );
    }
}
