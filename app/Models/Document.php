<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentEditScope;
use App\Enums\DocumentStatus;
use App\Enums\DocumentVersionKind;
use App\Enums\ExtractionStatus;
use App\Enums\PreviewStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Entitas utama sistem.
 *
 * Hak melihat dokumen tidak ditentukan satu kolom "tipe visibilitas",
 * melainkan kombinasi lima mekanisme akses yang berlaku bersamaan. Seluruh
 * evaluasinya terpusat di scope `visibleTo()` — ditambahkan pada FEAT-05 —
 * supaya tidak ada satu pun tempat di aplikasi yang menyusun aturan aksesnya
 * sendiri (`PRD.md` §2.6).
 */
#[Fillable([
    'nomor', 'judul', 'category_id', 'origin_unit_id',
    'tanggal', 'masa_berlaku', 'status', 'deskripsi',
    'file_path', 'file_name_original', 'file_mime_type', 'file_size', 'thumbnail_path', 'preview_path', 'preview_status', 'preview_message',
    'extracted_text', 'extraction_status', 'extraction_pages_total', 'extraction_pages_processed',
    'extraction_estimated_seconds', 'extraction_message', 'extraction_started_at',
    'nomor_normalized', 'replaces_document_id', 'version_root_id',
    'version_major', 'version_minor', 'version_kind', 'version_note',
    'is_shared_to_all', 'is_private', 'min_tingkat_akses', 'edit_scope',
    'uploaded_by', 'is_active', 'trashed_at', 'trashed_by', 'purge_after', 'trash_token',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $document): void {
            $document->nomor_normalized = preg_replace('/[^a-z0-9]+/i', '', strtolower($document->nomor)) ?? '';
        });

        // Baris awal baru belum memiliki ID ketika INSERT berjalan. Sesudahnya
        // ia menjadi akar untuk dirinya sendiri. Jalur produksi selalu mengisi
        // garis keturunan lewat DocumentVersionService, tetapi pengaman ini
        // juga menjaga impor/fixture yang hanya memberi replaces_document_id.
        static::created(function (self $document): void {
            if ($document->version_root_id !== null) {
                return;
            }

            $pendahulu = $document->replaces_document_id === null
                ? null
                : self::query()->find($document->replaces_document_id);

            $document->forceFill($pendahulu === null ? [
                'version_root_id' => $document->id,
            ] : [
                'version_root_id' => $pendahulu->version_root_id ?? $pendahulu->id,
                'version_major' => $pendahulu->version_major + 1,
                'version_minor' => 0,
                'version_kind' => DocumentVersionKind::Content,
            ])->saveQuietly();
        });
    }

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
        'file_name_original', 'file_mime_type', 'file_size', 'thumbnail_path', 'preview_path', 'preview_status',
        'is_shared_to_all', 'is_private', 'min_tingkat_akses',
        'uploaded_by', 'is_active', 'created_at',
        // Dipakai halaman Sampah lewat DocumentListData::untukWorkspace() —
        // tanpanya `$document->purge_after` selalu null walau kolomnya
        // terisi di database, karena baris ini tidak pernah ikut ter-SELECT.
        'trashed_at', 'purge_after',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'masa_berlaku' => 'date',
            'status' => DocumentStatus::class,
            'extraction_status' => ExtractionStatus::class,
            'preview_status' => PreviewStatus::class,
            'edit_scope' => DocumentEditScope::class,
            'version_kind' => DocumentVersionKind::class,
            'is_shared_to_all' => 'boolean',
            'is_private' => 'boolean',
            'is_active' => 'boolean',
            'trashed_at' => 'datetime',
            'purge_after' => 'datetime',
            'min_tingkat_akses' => 'integer',
            'file_size' => 'integer',
            'extraction_pages_total' => 'integer',
            'extraction_pages_processed' => 'integer',
            'extraction_estimated_seconds' => 'integer',
            'extraction_started_at' => 'datetime',
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

    /** Dokumen lama yang digantikan oleh unggahan ini. */
    public function replacedDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_document_id');
    }

    /** Dokumen baru yang menggantikan dokumen ini. */
    public function replacementDocument(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_document_id');
    }

    /** Versi pertama yang menjadi pemilik rantai dokumen ini. */
    public function versionRoot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'version_root_id');
    }

    /** @return HasMany<Document, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'version_root_id');
    }

    /** @return HasMany<DocumentPlacement, $this> */
    public function placements(): HasMany
    {
        return $this->hasMany(DocumentPlacement::class);
    }

    /** @return HasMany<DocumentStar, $this> */
    public function stars(): HasMany
    {
        return $this->hasMany(DocumentStar::class);
    }

    /** @return HasMany<DocumentRecent, $this> */
    public function recents(): HasMany
    {
        return $this->hasMany(DocumentRecent::class);
    }

    public function versionLabel(): string
    {
        return "v{$this->version_major}.{$this->version_minor}";
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

    /**
     * Satu dokumen paling banyak punya satu placement — hanya pengunggah yang
     * boleh menempatkan dokumennya sendiri (`DocumentWorkspaceService::placeDocument()`),
     * dan `document_placements` unik per `(owner_id, document_id)`.
     *
     * @return HasOne<DocumentPlacement, $this>
     */
    public function placement(): HasOne
    {
        return $this->hasOne(DocumentPlacement::class);
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
        if ($this->is_private) {
            return ['Hanya saya (Superadmin untuk audit)'];
        }

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
        return (int) $this->is_private
            + (int) $this->is_shared_to_all
            + (int) ($this->min_tingkat_akses !== null)
            + (int) $this->targetUnits->isNotEmpty()
            + (int) $this->sharedUsers->isNotEmpty();
    }

    /**
     * Mekanisme mana yang membuat dokumen ini terlihat bagi SATU pengguna
     * tertentu (FEAT-12) — beda dari `accessSummary()`, yang mendaftar
     * SELURUH mekanisme yang aktif secara global tanpa peduli siapa yang
     * melihat. Ini menjawab "kenapa saya bisa membuka dokumen ini".
     *
     * Urutan pengecekan mengikuti persis rantai OR di `scopeVisibleTo()`:
     * pelewatan (bypass) dan jaminan bawaan pengunggah dicek lebih dulu
     * karena keduanya berlaku di LUAR keempat mekanisme, baru diikuti
     * keempat mekanisme itu sendiri dalam urutan yang sama. Hanya SATU
     * alasan yang dikembalikan meski bisa saja lebih dari satu yang cocok —
     * cukup untuk menjawab pertanyaannya, bukan daftar lengkap seperti
     * `accessSummary()`.
     *
     * Memerlukan relasi `targetUnits` dan `sharedUsers` sudah dimuat, sama
     * seperti `accessSummary()`.
     */
    public function alasanTerlihat(User $user): string
    {
        if ($user->isSuperadmin()) {
            return 'Superadmin';
        }

        if ($this->uploaded_by === $user->id) {
            return 'Anda pengunggahnya';
        }

        if ($this->is_private) {
            return 'Tidak diketahui';
        }

        if ($user->isPimpinanTertinggi()) {
            return 'Jabatan tingkat 1';
        }

        if ($this->is_shared_to_all) {
            return 'Dibagikan ke semua pengguna';
        }

        $tingkatAkses = $user->jabatan?->tingkat_akses;

        if (
            $tingkatAkses !== null
            && $this->min_tingkat_akses !== null
            && $tingkatAkses <= $this->min_tingkat_akses
        ) {
            return 'Jenjang jabatan Anda';
        }

        if ($user->unit_id !== null && $this->targetUnits->contains('id', $user->unit_id)) {
            return 'Unit kerja Anda';
        }

        if ($this->sharedUsers->contains('id', $user->id)) {
            return 'Dibagikan langsung ke Anda';
        }

        $folderDibagikan = $this->folderTerdekatYangDibagikan($user);
        if ($folderDibagikan !== null) {
            return "Folder: {$folderDibagikan->name}";
        }

        // Semestinya tidak pernah tercapai: baris ini hanya pernah dimuat
        // lewat `visibleTo()`, yang menjamin salah satu di atas pasti benar.
        return 'Tidak diketahui';
    }

    /**
     * Folder tempat dokumen ini ditaruh, atau leluhur terdekatnya, yang
     * benar-benar dibagikan ke `$user` — dipakai `alasanTerlihat()` untuk
     * menjawab "kenapa saya bisa buka dokumen ini" lewat Mekanisme 5.
     * `accessSummary()` SENGAJA tidak diberi cabang serupa: itu meringkas
     * pengaturan yang diatur pengunggah pada dokumennya sendiri, sedangkan
     * akses lewat folder-share ditentukan di tempat lain (pengaturan
     * folder) dan bisa berubah tanpa dokumennya disentuh sama sekali.
     *
     * Relasinya dibaca sebagai properti, bukan lewat `exists()` per folder:
     * pemanggil daftar sudah memuat seluruh rantai ini di muka
     * (`DocumentListingService::relasiRantaiFolder()`), sehingga bentuk ini
     * tidak menembak kueri apa pun per baris — sementara `exists()` selalu
     * menembak dua kueri per level, dimuat di muka atau tidak.
     */
    private function folderTerdekatYangDibagikan(User $user): ?DocumentFolder
    {
        $folder = $this->placement?->folder;

        while ($folder !== null) {
            $milikUser = $folder->sharedUsers->contains('id', $user->id);
            $milikUnit = $user->unit_id !== null && $folder->targetUnits->contains('id', $user->unit_id);

            if ($milikUser || $milikUnit) {
                return $folder;
            }

            $folder = $folder->parent;
        }

        return null;
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
     * Lima mekanisme akses dievaluasi sebagai rantai OR — dokumen terlihat
     * bila SALAH SATU terpenuhi, berapa pun yang aktif bersamaan (`PRD.md`
     * §2.4). Ditambah satu jaminan bawaan: pengunggah selalu dapat melihat
     * dokumennya sendiri, di luar kombinasi yang ia atur.
     *
     * Mekanisme 1-4 diatur pengunggah pada dokumennya sendiri; Mekanisme 5
     * (folder yang dibagikan) diatur di tempat lain — pada folder "Dokumen
     * Saya" milik pengunggah — sehingga akses lewat jalur itu bisa berubah
     * tanpa dokumennya disentuh sama sekali.
     *
     * Perbedaan asal keputusan itulah alasan `$sertakanFolder` ada: hak
     * MELIHAT selalu memakai kelima mekanisme (bawaannya `true`, jadi seluruh
     * pemanggil lama tidak berubah), sedangkan `edit_scope` "Sama seperti
     * akses" hanya boleh mengikuti Mekanisme 1-4 — membagikan folder tidak
     * pernah dimaksudkan sebagai pemberian hak ubah.
     *
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeVisibleTo(Builder $query, User $user, bool $sertakanFolder = true): Builder
    {
        $query->notTrashed();

        // Superadmin tetap dapat mengaudit dokumen pribadi. Jabatan tingkat 1
        // hanya mem-bypass empat mekanisme berbagi, bukan keputusan eksplisit
        // "Hanya saya" milik pengunggah.
        if ($user->isSuperadmin()) {
            return $query;
        }

        if ($user->isPimpinanTertinggi()) {
            // Bypass keempat mekanisme berbagi, tapi pemilik dokumen tetap
            // harus dapat melihat dokumennya sendiri — termasuk yang ia
            // tandai "Hanya saya" — sama seperti jabatan lain mana pun.
            return $query->where(function (Builder $group) use ($user): void {
                $group->where('documents.uploaded_by', $user->id)
                    ->orWhere('documents.is_private', false);
            });
        }

        $tingkatAkses = $user->jabatan?->tingkat_akses;

        // Seluruh rantai OR dibungkus satu grup. Tanpa pembungkus ini,
        // penyaring yang ditambahkan controller sesudahnya (kategori, status,
        // kata kunci) akan tercampur ke dalam rantai OR dan meloloskan dokumen
        // yang seharusnya tertutup.
        return $query->where(function (Builder $group) use ($user, $tingkatAkses, $sertakanFolder): void {
            // Jaminan bawaan — bukan salah satu dari empat mekanisme.
            $group->where('documents.uploaded_by', $user->id);

            // Dokumen pribadi hanya berhenti pada pengunggah (di atas) atau
            // Superadmin (dikembalikan lebih awal), sekalipun request palsu
            // masih menyisakan data mekanisme akses lama.
            $group->orWhere(function (Builder $dibagikan) use ($user, $tingkatAkses, $sertakanFolder): void {
                $dibagikan->where('documents.is_private', false);

                // Mekanisme 1: bagikan ke semua pengguna internal.
                $dibagikan->where(function (Builder $mekanisme) use ($user, $tingkatAkses, $sertakanFolder): void {
                    $mekanisme->where('documents.is_shared_to_all', true);

                    // Mekanisme 2: jenjang jabatan. Angka tingkat yang lebih kecil
                    // berarti jenjang yang lebih tinggi, sehingga dokumen ber-ambang 2
                    // terlihat oleh tingkat 1 dan 2.
                    if ($tingkatAkses !== null) {
                        $mekanisme->orWhere(function (Builder $q) use ($tingkatAkses): void {
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
                        $mekanisme->orWhereHas(
                            'targetUnits',
                            fn (Builder $q) => $q->where('units.id', $user->unit_id),
                        );
                    }

                    // Mekanisme 4: orang tertentu.
                    $mekanisme->orWhereHas(
                        'sharedUsers',
                        fn (Builder $q) => $q->where('users.id', $user->id),
                    );

                    // Mekanisme 5 sengaja dapat dimatikan pemanggil.
                    // `DocumentPolicy::update()` memakainya untuk
                    // "Sama seperti akses": folder yang dibagikan
                    // memberi hak melihat, bukan hak mengubah.
                    if ($sertakanFolder) {
                        // Mekanisme 5: folder yang dibagikan (langsung atau
                        // lewat leluhurnya). Kedalaman folder dibatasi
                        // DocumentFolder::KEDALAMAN_MAKSIMAL, jadi self-join
                        // bertingkat tetap ini selalu cukup — dibangun dengan
                        // loop, bukan ditulis tangan, supaya jumlah levelnya
                        // otomatis ikut kalau batas itu berubah.
                        $mekanisme->orWhereExists(function (QueryBuilder $sub) use ($user): void {
                            $alias = [];
                            for ($i = 0; $i < DocumentFolder::KEDALAMAN_MAKSIMAL; $i++) {
                                $alias[] = "f{$i}";
                            }

                            $sub->selectRaw('1')
                                ->from('document_placements as dp')
                                ->join('document_folders as '.$alias[0], $alias[0].'.id', '=', 'dp.folder_id')
                                ->whereColumn('dp.document_id', 'documents.id');

                            // Menaiki rantai leluhur: `f{i}` adalah INDUK dari
                            // `f{i-1}`, jadi syaratnya `f{i}.id = f{i-1}.parent_id`
                            // — bukan kebalikannya. Arah yang tertukar akan
                            // menuruni rantai ke anak-anaknya dan membocorkan
                            // dokumen di folder induk begitu salah satu
                            // subfoldernya dibagikan.
                            for ($i = 1; $i < count($alias); $i++) {
                                $sub->leftJoin('document_folders as '.$alias[$i], $alias[$i].'.id', '=', $alias[$i - 1].'.parent_id');
                            }

                            // Rantai leluhur bisa berisi campuran folder hidup dan
                            // folder di Sampah — trash/restore per-folder (bukan
                            // cuma per-pohon) membuat itu mungkin: anak yang
                            // di-trash lebih dulu menyimpan trash_token sendiri,
                            // jadi ikut terlewat saat leluhurnya di-trash
                            // belakangan (`trashFolder()` memakai `notTrashed()`
                            // pada UPDATE-nya), lalu bisa dipulihkan sendiri lewat
                            // token-nya sementara leluhurnya tetap di Sampah.
                            // Karena itu TIDAK CUKUP memeriksa `f0` saja — setiap
                            // level dari `f0` sampai level yang benar-benar
                            // memberi akses harus hidup semua, bukan cuma level
                            // yang memberi akses itu sendiri.
                            $sub->where(function (QueryBuilder $rantai) use ($user, $alias): void {
                                $kolomTrashedSejauhIni = [];

                                foreach ($alias as $a) {
                                    $kolomTrashedSejauhIni[] = "{$a}.trashed_at";
                                    $prefikSampaiSini = $kolomTrashedSejauhIni;

                                    $rantai->orWhere(function (QueryBuilder $cabang) use ($user, $a, $prefikSampaiSini): void {
                                        foreach ($prefikSampaiSini as $kolom) {
                                            $cabang->whereNull($kolom);
                                        }

                                        $cabang->where(function (QueryBuilder $pemberi) use ($user, $a): void {
                                            $pemberi->orWhereExists(fn (QueryBuilder $q) => $q->selectRaw('1')
                                                ->from('document_folder_shares as dfs')
                                                ->whereColumn('dfs.folder_id', "{$a}.id")
                                                ->where('dfs.user_id', $user->id));

                                            if ($user->unit_id !== null) {
                                                $pemberi->orWhereExists(fn (QueryBuilder $q) => $q->selectRaw('1')
                                                    ->from('document_folder_units as dfu')
                                                    ->whereColumn('dfu.folder_id', "{$a}.id")
                                                    ->where('dfu.unit_id', $user->unit_id));
                                            }
                                        });
                                    });
                                }
                            });
                        });
                    }
                });
            });
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

    /** @param Builder<Document> $query @return Builder<Document> */
    public function scopeNotTrashed(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('trashed_at'));
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
