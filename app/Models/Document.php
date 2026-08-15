<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentEditScope;
use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

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

    // -- Ringkasan akses ------------------------------------------------------

    /**
     * Label mekanisme akses yang sedang aktif, untuk ditampilkan ke pengguna.
     *
     * Dihitung dari kolom dan relasi yang sama dengan yang dibaca
     * `scopeVisibleTo()`, sehingga label yang tampil tidak mungkin bertentangan
     * dengan akses yang sebenarnya berlaku. Ini yang menggantikan kolom `akses`
     * versi lama — teks bebas yang boleh diisi apa saja dan tidak pernah dibaca
     * logika otorisasi, sehingga sebuah dokumen bisa berlabel "Rahasia" padahal
     * dapat dibuka semua orang (`Catatan_Audit.md` isu #4).
     *
     * Memerlukan relasi `targetUnits` dan `sharedUsers` sudah dimuat. Pada
     * halaman daftar, muat keduanya lewat `with()` — tanpa itu setiap baris
     * menembakkan dua query tambahan.
     *
     * @return list<string>
     */
    public function accessSummary(): array
    {
        if ($this->is_shared_to_all) {
            return ['Semua pengguna'];
        }

        $bagian = [];

        if ($this->targetUnits->isNotEmpty()) {
            $bagian[] = $this->targetUnits->count() === 1
                ? 'Unit: '.$this->targetUnits->first()->nama
                : $this->targetUnits->count().' unit kerja';
        }

        if ($this->min_tingkat_akses !== null) {
            $bagian[] = "Jenjang jabatan tingkat {$this->min_tingkat_akses} ke atas";
        }

        if ($this->sharedUsers->isNotEmpty()) {
            $bagian[] = $this->sharedUsers->count().' orang tertentu';
        }

        // Bukan kesalahan: dokumen semacam ini hanya terlihat pengunggahnya,
        // Superadmin, dan jabatan tingkat 1. Dinyatakan apa adanya supaya
        // pengunggah menyadarinya, bukan disamarkan.
        return $bagian === [] ? ['Hanya pengunggah'] : $bagian;
    }

    /**
     * Berapa banyak dari empat mekanisme akses yang sedang aktif.
     */
    public function jumlahMekanismeAktif(): int
    {
        return (int) $this->is_shared_to_all
            + (int) ($this->min_tingkat_akses !== null)
            + (int) $this->targetUnits->isNotEmpty()
            + (int) $this->sharedUsers->isNotEmpty();
    }

    // -- Scope ----------------------------------------------------------------

    /**
     * Menyaring dokumen menjadi hanya yang berhak dilihat seorang pengguna.
     *
     * SATU-SATUNYA sumber kebenaran hak melihat dokumen. Setiap tempat yang
     * mengambil dokumen — daftar, pencarian, dasbor, unduhan, pratinjau — wajib
     * melewatinya. Tidak boleh ada tempat lain yang menyusun aturannya sendiri,
     * karena aturan yang tersebar akan menyimpang satu sama lain tanpa ada yang
     * menyadarinya sampai data bocor.
     *
     * Penyaringan dikerjakan basis data lewat klausa `WHERE`, bukan dengan
     * mengambil semua baris lalu menyaringnya di PHP. Selain mencegah data di
     * luar hak pengguna masuk ke memori aplikasi, ini juga yang membuat
     * pagination menghitung jumlah halaman dari data yang benar.
     *
     * Empat mekanisme akses dievaluasi sebagai rantai OR — dokumen terlihat
     * bila SALAH SATU terpenuhi, berapa pun yang aktif bersamaan (`PRD.md`
     * §2.4). Ditambah satu jaminan bawaan: pengunggah selalu dapat melihat
     * dokumennya sendiri, di luar kombinasi yang ia atur.
     *
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // Superadmin dan jabatan tingkat 1 melihat seluruh dokumen tanpa
        // terikat mekanisme apa pun (FR-44).
        if ($user->bypassesDocumentAccess()) {
            return $query;
        }

        $tingkatAkses = $user->jabatan?->tingkat_akses;

        // Seluruh rantai OR dibungkus satu grup. Tanpa pembungkus ini,
        // penyaring yang ditambahkan controller sesudahnya (kategori, status,
        // kata kunci) akan tercampur ke dalam rantai OR dan meloloskan dokumen
        // yang seharusnya tertutup.
        return $query->where(function (Builder $group) use ($user, $tingkatAkses): void {
            // Jaminan bawaan — bukan salah satu dari empat mekanisme.
            $group->where('documents.uploaded_by', $user->id);

            // Mekanisme 1: bagikan ke semua pengguna internal.
            $group->orWhere('documents.is_shared_to_all', true);

            // Mekanisme 2: jenjang jabatan. Angka tingkat yang lebih kecil
            // berarti jenjang yang lebih tinggi, sehingga dokumen ber-ambang 2
            // terlihat oleh tingkat 1 dan 2.
            if ($tingkatAkses !== null) {
                $group->orWhere(function (Builder $q) use ($tingkatAkses): void {
                    $q->whereNotNull('documents.min_tingkat_akses')
                        ->where('documents.min_tingkat_akses', '>=', $tingkatAkses);
                });
            }

            // Mekanisme 3: unit kerja.
            //
            // Hanya kecocokan langsung. Cascade ke divisi bawahan sudah
            // diselesaikan saat menyimpan oleh `DocumentUnitResolver`, sehingga
            // isi `document_units` selalu mencerminkan persis siapa yang
            // berhak. Menambahkan kondisi `units.parent_id` di sini akan
            // menjalankan cascade untuk kedua kalinya dan membuat pengurangan
            // unit secara manual oleh pengunggah diam-diam tidak berlaku —
            // bertentangan dengan FR-39 (`Catatan_Audit.md` isu #15).
            if ($user->unit_id !== null) {
                $group->orWhereHas(
                    'targetUnits',
                    fn (Builder $q) => $q->where('units.id', $user->unit_id),
                );
            }

            // Mekanisme 4: orang tertentu.
            $group->orWhereHas(
                'sharedUsers',
                fn (Builder $q) => $q->where('users.id', $user->id),
            );
        });
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeActive(Builder $query): Builder
    {
        // Kolom dikualifikasi dengan nama tabel: `categories` dan `units` juga
        // punya `is_active`, sehingga scope ini akan ambigu begitu dipakai
        // bersama join — galat yang baru muncul jauh setelah scope ditulis.
        return $query->where($query->qualifyColumn('is_active'), true);
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
            ->where($query->qualifyColumn('status'), DocumentStatus::Berlaku)
            ->whereNotNull($query->qualifyColumn('masa_berlaku'))
            ->whereBetween($query->qualifyColumn('masa_berlaku'), [
                now()->toDateString(),
                now()->addDays($hari)->toDateString(),
            ]);
    }
}
