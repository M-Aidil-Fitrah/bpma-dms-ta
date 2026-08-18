<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DocumentEditScope;
use App\Models\Document;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Keadaan dokumen sebagaimana dibutuhkan FORMULIR ubah (FR-08, FR-42).
 *
 * Berbeda dari `DocumentDetailData` yang menyiapkan data untuk DIBACA, DTO ini
 * menyiapkan data untuk DIISIKAN kembali ke formulir. Perbedaannya nyata: di
 * sini yang dikirim adalah `id` unit dan orang, bukan namanya. Formulir harus
 * dapat mencentang ulang pilihan yang sedang berlaku, dan nama tidak dapat
 * dicocokkan kembali ke baris basis data.
 *
 * `extracted_text` sengaja tidak ada di sini — formulir ubah tidak pernah
 * menampilkannya, dan kolom itu dapat berukuran megabyte.
 */
#[TypeScript]
final class DocumentEditData extends Data
{
    /**
     * @param  list<int>  $unit_ids
     * @param  list<array{id: int, nama: string, jabatan: string|null, unit: string|null}>  $orang_tertentu
     */
    public function __construct(
        public int $id,
        public string $nomor,
        public string $judul,
        public ?string $deskripsi,
        public ?int $category_id,
        public ?int $origin_unit_id,
        public string $tanggal,
        public ?string $masa_berlaku,

        // -- Mekanisme akses yang sedang berlaku ------------------------------
        public bool $is_shared_to_all,
        public ?int $min_tingkat_akses,
        public array $unit_ids,
        public array $orang_tertentu,
        public DocumentEditScope $edit_scope,

        // -- Berkas: hanya untuk ditampilkan, tidak dapat diganti -------------
        public string $nama_berkas,
        public string $tipe_berkas,
        public int $ukuran_berkas,
        public bool $thumbnail_tersedia,
    ) {}

    public static function fromModel(Document $document): self
    {
        return new self(
            id: $document->id,
            nomor: $document->nomor,
            judul: $document->judul,
            deskripsi: $document->deskripsi,
            category_id: $document->category_id,
            origin_unit_id: $document->origin_unit_id,
            tanggal: $document->tanggal->toDateString(),
            masa_berlaku: $document->masa_berlaku?->toDateString(),

            is_shared_to_all: $document->is_shared_to_all,
            min_tingkat_akses: $document->min_tingkat_akses,
            unit_ids: $document->targetUnits->pluck('id')->map(intval(...))->all(),
            orang_tertentu: $document->sharedUsers
                ->map(fn ($pengguna): array => [
                    'id' => (int) $pengguna->id,
                    'nama' => $pengguna->name,
                    'jabatan' => $pengguna->jabatan?->nama,
                    'unit' => $pengguna->unit?->nama,
                ])
                ->values()
                ->all(),
            edit_scope: $document->edit_scope,

            nama_berkas: $document->file_name_original,
            tipe_berkas: $document->file_mime_type,
            ukuran_berkas: $document->file_size,
            thumbnail_tersedia: $document->thumbnail_path !== null,
        );
    }
}
