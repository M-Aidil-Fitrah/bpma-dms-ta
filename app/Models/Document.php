<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentEditScope;
use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Entitas utama sistem.
 *
 * Hak melihat dokumen tidak ditentukan satu kolom "tipe visibilitas",
 * melainkan kombinasi empat mekanisme akses yang berlaku bersamaan. Seluruh
 * evaluasinya terpusat di scope `visibleTo()` — ditambahkan pada FEAT-05 —
 * supaya tidak ada satu pun tempat di aplikasi yang menyusun aturan aksesnya
 * sendiri (`PRD.md` §2.6).
 */
#[Fillable([
    'nomor', 'judul', 'category_id', 'origin_unit_id',
    'tanggal', 'masa_berlaku', 'status', 'deskripsi',
    'file_path', 'file_name_original', 'file_mime_type', 'file_size',
    'extracted_text', 'extraction_status',
    'is_shared_to_all', 'min_tingkat_akses', 'edit_scope',
    'uploaded_by', 'is_active',
])]
class Document extends Model
{
    /**
     * Kolom yang aman dimuat pada halaman daftar dan hasil pencarian.
     *
     * `extracted_text` sengaja TIDAK ada di sini. Kolomnya bertipe `longText`
     * dan dapat berukuran megabyte per baris — memuatnya untuk 20 baris berarti
     * menyeret puluhan megabyte ke memori demi data yang tidak pernah
     * ditampilkan. Kolom itu hanya boleh dimuat pada halaman detail.
     *
     * @var list<string>
     */
    public const KOLOM_DAFTAR = [
        'id', 'nomor', 'judul', 'category_id', 'origin_unit_id',
        'tanggal', 'masa_berlaku', 'status', 'extraction_status',
        'file_mime_type', 'file_size',
        'is_shared_to_all', 'min_tingkat_akses',
        'uploaded_by', 'is_active', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'masa_berlaku' => 'date',
            'status' => DocumentStatus::class,
            'extraction_status' => ExtractionStatus::class,
            'edit_scope' => DocumentEditScope::class,
            'is_shared_to_all' => 'boolean',
            'is_active' => 'boolean',
            'min_tingkat_akses' => 'integer',
            'file_size' => 'integer',
        ];
    }

    // -- Relasi ---------------------------------------------------------------

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Unit asal dokumen — penanda kepemilikan untuk penyaringan (FR-18).
     * Tidak memberi hak akses kepada siapa pun.
     *
     * @return BelongsTo<Unit, $this>
     */
    public function originUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'origin_unit_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Mekanisme akses "bagikan ke unit" (FR-39).
     *
     * @return BelongsToMany<Unit, $this>
     */
    public function targetUnits(): BelongsToMany
    {
        // Tanpa `withTimestamps()`: tabel pivot hanya punya `created_at`, dan
        // helper itu selalu ikut menulis `updated_at` yang tidak ada di sana.
        // Nilai `created_at` diisi otomatis oleh default `useCurrent()` skema.
        return $this->belongsToMany(Unit::class, 'document_units')
            ->withPivot('added_by', 'created_at');
    }

    /**
     * Mekanisme akses "bagikan ke orang tertentu" (FR-41).
     *
     * @return BelongsToMany<User, $this>
     */
    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'document_shares')
            ->withPivot('granted_by', 'created_at');
    }

    // -- Scope ----------------------------------------------------------------

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Dokumen yang masa berlakunya jatuh dalam sekian hari ke depan (FR-04).
     *
     * Rentangnya dipilih pengguna di dasbor — bukan properti dokumen. Masa
     * berlaku tiap dokumen ditentukan pengunggah lewat kolom `masa_berlaku`.
     *
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeMendekatiMasaEvaluasi(Builder $query, int $hari): Builder
    {
        return $query
            ->where('status', DocumentStatus::Berlaku)
            ->whereNotNull('masa_berlaku')
            ->whereBetween('masa_berlaku', [
                now()->toDateString(),
                now()->addDays($hari)->toDateString(),
            ]);
    }
}
