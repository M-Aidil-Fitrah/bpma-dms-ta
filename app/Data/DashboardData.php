<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ringkasan dokumen pada dasbor.
 *
 * Seluruh angka di sini dihitung dari dokumen yang benar-benar berhak dilihat
 * pengguna yang sedang masuk — dua akun berbeda melihat dasbor yang berbeda
 * (FR-01 s.d. FR-05).
 */
#[TypeScript]
final class DashboardData extends Data
{
    /**
     * @param  list<KategoriRingkasData>  $per_kategori
     * @param  list<DocumentListData>  $terbaru
     * @param  list<DocumentListData>  $mendekati_evaluasi
     * @param  list<int>  $rentang_pilihan
     */
    public function __construct(
        public int $total,
        public int $berlaku,
        public int $kadaluarsa,
        public int $jumlah_mendekati_evaluasi,
        public array $per_kategori,
        public array $terbaru,
        public array $mendekati_evaluasi,
        /** Rentang hari yang sedang dipilih untuk kartu masa evaluasi. */
        public int $rentang_evaluasi,
        public array $rentang_pilihan,
    ) {}
}
