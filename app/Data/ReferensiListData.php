<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Category;
use App\Models\Jabatan;
use App\Models\Unit;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Satu baris daftar jabatan, unit, atau kategori pada manajemen organisasi. */
#[TypeScript]
final class ReferensiListData extends Data
{
    /**
     * @param  list<string>  $dampak_nonaktif
     */
    public function __construct(
        public int $id,
        public string $nama,
        public string $jenis,
        public ?string $keterangan,
        public bool $is_active,
        public int $kedalaman,
        public array $dampak_nonaktif,
    ) {}

    public static function dariJabatan(Jabatan $jabatan): self
    {
        return new self(
            id: $jabatan->id,
            nama: $jabatan->nama,
            jenis: 'jabatan',
            keterangan: "Tingkat akses {$jabatan->tingkat_akses}",
            is_active: $jabatan->is_active,
            kedalaman: 0,
            dampak_nonaktif: self::dampak(['pengguna' => (int) $jabatan->users_count]),
        );
    }

    public static function dariUnit(Unit $unit, int $kedalaman): self
    {
        $induk = $unit->parent?->nama;

        return new self(
            id: $unit->id,
            nama: $unit->nama,
            jenis: 'unit',
            keterangan: trim(implode(' · ', array_filter([
                ucfirst($unit->tipe),
                $induk === null ? 'Unit tingkat atas' : "Di bawah {$induk}",
            ]))),
            is_active: $unit->is_active,
            kedalaman: $kedalaman,
            dampak_nonaktif: self::dampak([
                'pengguna' => (int) $unit->users_count,
                'dokumen asal' => (int) $unit->originated_documents_count,
                'akses dokumen' => (int) $unit->shared_documents_count,
            ]),
        );
    }

    public static function dariKategori(Category $category): self
    {
        return new self(
            id: $category->id,
            nama: $category->nama,
            jenis: 'kategori',
            keterangan: $category->deskripsi,
            is_active: $category->is_active,
            kedalaman: 0,
            dampak_nonaktif: self::dampak(['dokumen' => (int) $category->documents_count]),
        );
    }

    /**
     * @param  array<string, int>  $angka
     * @return list<string>
     */
    private static function dampak(array $angka): array
    {
        return collect($angka)
            ->filter(static fn (int $jumlah): bool => $jumlah > 0)
            ->map(static fn (int $jumlah, string $label): string => "{$jumlah} {$label}")
            ->values()
            ->all();
    }
}
