<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Category;
use App\Models\Jabatan;
use App\Models\Unit;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Nilai satu data referensi ketika dibuka pada formulir ubah.
 *
 * Satu DTO dipakai tiga formulir karena semua mempunyai nama dan status yang
 * sama; medan khusus dibiarkan nullable agar antarmuka tidak perlu memelihara
 * tiga kontrak yang hampir identik.
 */
#[TypeScript]
final class ReferensiEditData extends Data
{
    public function __construct(
        public int $id,
        public string $nama,
        public bool $is_active,
        public ?int $tingkat_akses = null,
        public ?int $parent_id = null,
        public ?string $tipe = null,
        public ?string $deskripsi = null,
    ) {}

    public static function dariJabatan(Jabatan $jabatan): self
    {
        return new self(
            id: $jabatan->id,
            nama: $jabatan->nama,
            is_active: $jabatan->is_active,
            tingkat_akses: $jabatan->tingkat_akses,
        );
    }

    public static function dariUnit(Unit $unit): self
    {
        return new self(
            id: $unit->id,
            nama: $unit->nama,
            is_active: $unit->is_active,
            parent_id: $unit->parent_id,
            tipe: $unit->tipe,
        );
    }

    public static function dariKategori(Category $category): self
    {
        return new self(
            id: $category->id,
            nama: $category->nama,
            is_active: $category->is_active,
            deskripsi: $category->deskripsi,
        );
    }
}
