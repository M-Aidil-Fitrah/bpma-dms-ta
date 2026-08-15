<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\DocumentAccessChanges;
use App\Data\DocumentDetailData;
use App\Data\DocumentEditData;
use App\Data\DocumentListData;
use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use App\Http\Requests\DocumentIndexRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Category;
use App\Models\Document;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogQuery;
use App\Services\ActivityLogService;
use App\Services\DocumentAccessWriter;
use App\Services\DocumentMetadataChanges;
use App\Services\DocumentUploadService;
use App\Services\PengaturanService;
use App\Support\BatasUnggah;
use App\Support\JenjangAkses;
use App\Support\PenyajianBerkas;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Daftar dokumen — halaman inti aplikasi (FR-16 s.d. FR-22).
 *
 * Pencarian di sini masih terbatas judul dan nomor. Pencarian berbasis isi
 * dokumen lewat index FULLTEXT menyusul bersama modul pencarian lanjutan.
 */
final class DocumentController extends Controller
{
    public function index(DocumentIndexRequest $request, PengaturanService $pengaturan): Response
    {
        $this->authorize('viewAny', Document::class);

        return Inertia::render('Documents/Index', [
            'dokumen' => $this->daftar($request, $pengaturan),
            'filter' => $request->filterAktif(),

            // Closure biasa, bukan `Inertia::optional()`. Keduanya sama-sama
            // menunda evaluasi, tapi prop opsional TIDAK ikut terkirim pada
            // muat awal — dropdown penyaringnya akan kosong sampai pengguna
            // melakukan sesuatu. Closure biasa dievaluasi saat muat awal, lalu
            // dilewati pada muat ulang parsial yang hanya meminta `dokumen`
            // dan `filter`.
            'opsi' => fn (): array => $this->opsiFilter(),
        ]);
    }

    /**
     * Mencari pengguna untuk mekanisme akses "orang tertentu" (FR-41).
     *
     * Nama unit ikut dikembalikan karena nama orang saja tidak cukup untuk
     * membedakan: pada organisasi sebesar ini, nama yang mirip di unit berbeda
     * adalah hal biasa, dan salah pilih berarti dokumen terbuka bagi orang yang
     * keliru.
     *
     * @return array<int, array{id: int, nama: string, jabatan: string|null, unit: string|null}>
     */
    public function cariPengguna(Request $request): array
    {
        $this->authorize('create', Document::class);

        $kata = trim((string) $request->string('cari'));

        if (mb_strlen($kata) < 2) {
            // Tanpa ambang ini, kolom pencarian yang baru diketik satu huruf
            // akan menarik hampir seluruh tabel pengguna.
            return [];
        }

        return User::query()
            ->active()
            ->whereNot('id', $request->user()->id)
            ->where('name', 'like', "%{$kata}%")
            ->with(['jabatan:id,nama', 'unit:id,nama'])
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'jabatan_id', 'unit_id'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'nama' => $u->name,
                'jabatan' => $u->jabatan?->nama,
                'unit' => $u->unit?->nama,
            ])
            ->all();
    }

    /**
     * Formulir unggah dokumen baru (FR-06).
     */
    public function create(): Response
    {
        $this->authorize('create', Document::class);

        return Inertia::render('Documents/Create', [
            'opsi' => $this->opsiFormulir(),
        ]);
    }

    /**
     * Menyimpan dokumen baru beserta seluruh mekanisme aksesnya.
     *
     * Berkas ditulis ke disk LEBIH DULU, baru barisnya disimpan. Urutan itu
     * tidak dapat dibalik — ukuran dan tipe MIME sesungguhnya baru diketahui
     * setelah berkasnya ada. Konsekuensinya, kegagalan di tengah jalan
     * menyisakan berkas yang tidak dirujuk baris mana pun. Karena itu bagian
     * basis data dibungkus transaksi, dan berkasnya dihapus bila transaksi itu
     * gagal.
     */
    public function store(
        StoreDocumentRequest $request,
        DocumentUploadService $uploader,
        DocumentAccessWriter $akses,
        ActivityLogService $aktivitas,
    ): RedirectResponse {
        $this->authorize('create', Document::class);

        $berkas = $uploader->store($request->file('file'));

        try {
            $document = DB::transaction(function () use ($request, $berkas, $akses, $aktivitas): Document {
                $document = Document::create([
                    ...$request->kolomDokumen(),
                    ...$berkas,
                    'status' => DocumentStatus::Berlaku,
                    'uploaded_by' => $request->user()->id,
                    'is_active' => true,
                ]);

                $perubahanAkses = $akses->sinkron(
                    $document,
                    $request->unitIds(),
                    $request->penerimaIds(),
                    $request->user(),
                );

                $aktivitas->record(
                    ActivityLogName::Dokumen,
                    AuditEvent::DocumentUploaded,
                    'Dokumen diunggah.',
                    $document,
                    $request->user(),
                    [
                        'mekanisme_akses' => $this->mekanismeAkses($document, $perubahanAkses),
                    ],
                );

                return $document;
            });
        } catch (Throwable $e) {
            // Tanpa pembersihan ini, setiap kegagalan meninggalkan berkas yang
            // tidak dirujuk baris mana pun — tidak terlihat siapa pun sampai
            // cakram penuh.
            $uploader->hapus($berkas['file_path']);

            throw $e;
        }

        // Dipicu SETELAH transaksi berhasil, bukan di dalamnya — job yang
        // sempat berjalan sebelum commit akan membaca baris yang belum ada
        // (Progres-dan-Lanjutan.md §7.2).
        if ($document->extraction_status === ExtractionStatus::Pending) {
            ExtractDocumentTextJob::dispatch($document);
        }

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'Dokumen berhasil diunggah.');
    }

    /**
     * Formulir ubah dokumen (FR-08, FR-42).
     *
     * Nilai akses yang sedang berlaku ikut dikirim supaya formulir terbuka
     * dengan keadaan sekarang, bukan kosong. Formulir yang kosong akan membuat
     * penyunting yang hanya ingin memperbaiki satu huruf pada judul tanpa sadar
     * mencabut seluruh daftar aksesnya.
     */
    public function edit(Document $document): Response
    {
        $this->authorize('update', $document);

        $document->load([
            'targetUnits:id,nama',
            'sharedUsers:id,name,jabatan_id,unit_id',
            'sharedUsers.jabatan:id,nama',
            'sharedUsers.unit:id,nama',
        ]);

        return Inertia::render('Documents/Edit', [
            'dokumen' => DocumentEditData::fromModel($document),
            'opsi' => $this->opsiFormulir(),
        ]);
    }

    /**
     * Menyimpan perubahan metadata dan daftar akses (FR-08, FR-42).
     *
     * Berkasnya tidak ikut disentuh sama sekali — lihat `UpdateDocumentRequest`.
     */
    public function update(
        UpdateDocumentRequest $request,
        Document $document,
        DocumentAccessWriter $akses,
        DocumentMetadataChanges $metadata,
        ActivityLogService $aktivitas,
    ): RedirectResponse {
        $this->authorize('update', $document);

        DB::transaction(function () use ($request, $document, $akses, $metadata, $aktivitas): void {
            $document->fill($request->kolomDokumen());
            $perubahanMetadata = $metadata->fromDirty($document);
            $document->save();

            $perubahanAkses = $akses->sinkron(
                $document,
                $request->unitIds(),
                $request->penerimaIds(),
                $request->user(),
            );

            if ($perubahanMetadata['before'] !== []) {
                $aktivitas->record(
                    ActivityLogName::Dokumen,
                    AuditEvent::DocumentUpdated,
                    'Informasi dokumen diperbarui.',
                    $document,
                    $request->user(),
                    before: $perubahanMetadata['before'],
                    after: $perubahanMetadata['after'],
                );
            }

            $this->catatPerubahanAkses($aktivitas, $document, $request->user(), $perubahanAkses);
        });

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'Perubahan dokumen berhasil disimpan.');
    }

    /**
     * Menonaktifkan dokumen (FR-10).
     *
     * Barisnya sengaja TIDAK dihapus. Dokumen yang pernah dibagikan menjadi
     * bagian dari riwayat organisasi: siapa mengunggah, siapa pernah membuka,
     * kapan aksesnya berubah. Menghapus barisnya berarti menghapus jawaban atas
     * pertanyaan-pertanyaan itu, dan pertanyaan itu justru muncul setelah ada
     * masalah.
     */
    public function destroy(Request $request, Document $document, ActivityLogService $aktivitas): RedirectResponse
    {
        $this->authorize('delete', $document);

        DB::transaction(function () use ($document, $request, $aktivitas): void {
            $document->update(['is_active' => false]);

            $aktivitas->record(
                ActivityLogName::Dokumen,
                AuditEvent::DocumentDeactivated,
                'Dokumen dinonaktifkan.',
                $document,
                $request->user(),
            );
        });

        return redirect()
            ->route('documents.index')
            ->with('success', "Dokumen \"{$document->judul}\" dinonaktifkan dan tidak lagi tampil di daftar.");
    }

    /**
     * Mengaktifkan kembali dokumen yang dinonaktifkan (FR-10).
     *
     * Hanya Superadmin — lihat alasannya di `DocumentPolicy::restore()`.
     */
    public function restore(Request $request, Document $document, ActivityLogService $aktivitas): RedirectResponse
    {
        $this->authorize('restore', $document);

        DB::transaction(function () use ($document, $request, $aktivitas): void {
            $document->update(['is_active' => true]);

            $aktivitas->record(
                ActivityLogName::Dokumen,
                AuditEvent::DocumentRestored,
                'Dokumen diaktifkan kembali.',
                $document,
                $request->user(),
            );
        });

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'Dokumen diaktifkan kembali.');
    }

    /**
     * Halaman detail satu dokumen (FR-07).
     *
     * Berbeda dari daftar, di sini `extracted_text` justru dimuat — panel teks
     * pratinjau untuk berkas non-visual dibangun dari kolom itu. Batasan yang
     * berlaku di halaman daftar tidak berlaku di sini karena yang diambil hanya
     * satu baris, bukan dua puluh.
     */
    public function show(Request $request, Document $document, ActivityLogQuery $aktivitas): Response
    {
        $this->authorize('view', $document);

        $document->load([
            'category:id,nama',
            'originUnit:id,nama',
            'uploader:id,name,jabatan_id,unit_id',
            'uploader.jabatan:id,nama',
            'uploader.unit:id,nama',
            'targetUnits:id,nama',
            'sharedUsers:id,name',
        ]);

        return Inertia::render('Documents/Show', [
            'dokumen' => DocumentDetailData::fromModel(
                $document,
                bolehUbah: $request->user()->can('update', $document),
                bolehAktifkan: $request->user()->can('restore', $document),
            ),
            'riwayat' => $aktivitas->recentForDocument($document),
        ]);
    }

    /**
     * Mengunduh berkas dokumen (FR-09).
     *
     * Wajib lewat rute ter-otorisasi, bukan tautan langsung ke penyimpanan.
     * Berkas berada di disk `local` yang tidak dapat dijangkau dari luar —
     * tanpa aturan ini, seluruh sistem mekanisme akses dapat dilewati hanya
     * dengan menebak alamat berkasnya (`PRD.md` §8.2).
     */
    public function serveFile(Request $request, Document $document, ActivityLogService $aktivitas): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        $aktivitas->record(
            ActivityLogName::Dokumen,
            AuditEvent::DocumentDownloaded,
            'Berkas dokumen diunduh.',
            $document,
            $request->user(),
            ['nama_berkas' => $document->file_name_original],
        );

        return Storage::disk('local')->download(
            $document->file_path,
            $document->file_name_original,
            [
                ...PenyajianBerkas::headerKeamanan(),
                // Bahkan pada unduhan, tipe berkas tidak diteruskan apa adanya.
                // `Content-Disposition: attachment` memang sudah menyuruh
                // peramban menyimpan, tapi tidak semua peramban lama patuh —
                // dan tipe generik menutup sisa celahnya.
                'Content-Type' => PenyajianBerkas::tipeAman($document->file_mime_type),
            ],
        );
    }

    /**
     * Menampilkan berkas di dalam peramban (FR-09b).
     *
     * Proteksinya IDENTIK dengan unduhan — satu-satunya perbedaan adalah header
     * `Content-Disposition`. Rute pratinjau yang lebih longgar akan menjadi
     * pintu belakang menuju berkas yang sama.
     *
     * Namun tidak semua tipe boleh tampil inline. Berkas HTML dan SVG dapat
     * memuat skrip, dan menampilkannya pada asal aplikasi berarti skrip itu
     * berjalan di dalam sesi orang yang membukanya. Tipe di luar daftar-boleh
     * karena itu tetap dilayani, tapi dipaksa menjadi unduhan — pengguna tidak
     * kehilangan aksesnya ke berkas, hanya tidak dijalankan di tempat.
     */
    public function previewFile(Request $request, Document $document, ActivityLogService $aktivitas): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        if (! PenyajianBerkas::bolehInline($document->file_mime_type)) {
            return $this->serveFile($request, $document, $aktivitas);
        }

        return Storage::disk('local')->response(
            $document->file_path,
            $document->file_name_original,
            [
                ...PenyajianBerkas::headerKeamanan(),
                // Tipe diambil dari daftar-boleh, bukan dari kolom apa adanya,
                // supaya nilai yang aneh di basis data tidak ikut diteruskan
                // mentah-mentah ke peramban.
                'Content-Type' => PenyajianBerkas::tipeAman($document->file_mime_type),
            ],
        );
    }

    /**
     * @return LengthAwarePaginator<int, DocumentListData>
     */
    private function daftar(DocumentIndexRequest $request, PengaturanService $pengaturan): LengthAwarePaginator
    {
        $user = $request->user();

        return Document::query()
            ->visibleTo($user)
            ->active()
            // Kolom dibatasi eksplisit. `extracted_text` bertipe longText dan
            // dapat berukuran megabyte per baris — memuatnya untuk dua puluh
            // baris berarti menyeret puluhan megabyte demi data yang tidak
            // ditampilkan sama sekali di daftar.
            ->select(Document::KOLOM_DAFTAR)
            ->with([
                'category:id,nama',
                'originUnit:id,nama',
                'uploader:id,name,jabatan_id',
                'uploader.jabatan:id,nama',
                // Dua relasi ini dimuat karena daftar memang menampilkan
                // ringkasan mekanisme akses di tiap baris.
                'targetUnits:id,nama',
                'sharedUsers:id',
            ])
            ->when(
                $request->string('cari')->toString(),
                fn ($query, string $kata) => $query->where(fn ($q) => $this->terapkanPencarian($q, $kata)),
            )
            ->when(
                $request->integer('kategori'),
                fn ($query, int $id) => $query->where('documents.category_id', $id),
            )
            ->when(
                $request->integer('unit'),
                fn ($query, int $id) => $query->where('documents.origin_unit_id', $id),
            )
            ->when(
                $request->string('status')->toString(),
                fn ($query, string $status) => $query->where('documents.status', $status),
            )
            ->when(
                $request->string('dari')->toString(),
                fn ($query, string $tanggal) => $query->whereDate('documents.tanggal', '>=', $tanggal),
            )
            ->when(
                $request->string('sampai')->toString(),
                fn ($query, string $tanggal) => $query->whereDate('documents.tanggal', '<=', $tanggal),
            )
            ->orderBy($request->kolomUrutan(), $request->arahUrutan())
            // Pengurutan kedua menjaga urutan tetap sama antar halaman. Tanpa
            // ini, baris dengan tanggal kembar dapat berpindah halaman di
            // antara dua permintaan — dokumen yang sama muncul dua kali, atau
            // hilang sama sekali.
            ->orderBy('documents.id', 'desc')
            ->paginate($pengaturan->integer('dokumen.per_halaman') ?? (int) config('dms.dokumen.per_halaman'))
            ->withQueryString()
            ->through(fn (Document $document): DocumentListData => DocumentListData::fromModel($document, $user));
    }

    /**
     * Pencarian isi dokumen lewat index FULLTEXT (FR-34).
     *
     * `nomor` sengaja ikut di DALAM index FULLTEXT yang sama dengan
     * judul/deskripsi/isi (migration `add_nomor_to_documents_fulltext_index`),
     * bukan dicocokkan terpisah lewat `LIKE` yang di-`OR`-kan. Percobaan
     * awal menggabungkan `MATCH...AGAINST` dengan `OR nomor LIKE` terbukti
     * lewat `EXPLAIN` membuat MariaDB berhenti memakai index FULLTEXT sama
     * sekali (`type` jatuh ke `ALL`, pemindaian tabel penuh) — kombinasi
     * MATCH dengan OR ke kolom lain mematikan optimasinya.
     *
     * Mode `boolean` dipakai, bukan mode alami bawaan: mode alami
     * menyingkirkan kata yang muncul di lebih dari separuh baris tabel
     * (ambang relevansi bawaan MySQL) begitu tabelnya punya cukup baris —
     * perilaku yang bisa membuat kata kunci yang jelas-jelas cocok tiba-tiba
     * tidak ditemukan, tanpa galat apa pun.
     *
     * @param  Builder<Document>  $query
     */
    private function terapkanPencarian(Builder $query, string $kata): void
    {
        $kata = trim($kata);

        // InnoDB (`innodb_ft_min_token_size`, bawaan 3) tidak mengindeks kata
        // di bawah 3 huruf sama sekali — mencarinya lewat FULLTEXT tidak akan
        // pernah menemukan apa pun walau kata itu ada berkali-kali di dalam
        // teks. Jaring pengaman `LIKE` untuk kasus ini sengaja dibatasi ke
        // judul dan nomor, tidak menyentuh `extracted_text`: memindai teks
        // sepanjang isi dokumen dengan `LIKE` untuk tiap baris meniadakan
        // keuntungan performa yang justru menjadi alasan FEAT-12 ada.
        if (mb_strlen($kata) < 3) {
            $query
                ->where('documents.judul', 'like', "%{$kata}%")
                ->orWhere('documents.nomor', 'like', "%{$kata}%");

            return;
        }

        $query->whereFullText(
            ['documents.nomor', 'documents.judul', 'documents.deskripsi', 'documents.extracted_text'],
            $kata,
            ['mode' => 'boolean'],
        );
    }

    /**
     * Pilihan yang mengisi formulir unggah.
     *
     * @return array<string, mixed>
     */
    private function opsiFormulir(): array
    {
        return [
            ...$this->opsiFilter(),

            // Bukan sekadar daftar angka: tiap tingkat dikirim beserta nama
            // jabatan dan jumlah pemegangnya, supaya formulir dapat menyebutkan
            // siapa saja yang tercakup alih-alih menuntut pengunggah menebak
            // arti "tingkat 2".
            'jenjang_jabatan' => JenjangAkses::daftar(),

            // Batas dikirim ke antarmuka supaya berkas kebesaran dapat ditolak
            // sebelum satu byte pun terkirim — jauh lebih baik daripada
            // menunggu unggahan panjang selesai hanya untuk ditolak.
            'batas_unggah_kb' => BatasUnggah::kilobyte(),
            'batas_unggah_label' => BatasUnggah::keterangan(),
            'batas_dijanjikan_label' => BatasUnggah::keteranganBatasAplikasi(),
            'lingkungan_kurang' => BatasUnggah::dibatasiPhp(),
        ];
    }

    /**
     * Pilihan yang mengisi kolom penyaring.
     *
     * Dikirim sebagai props opsional: isinya tidak pernah berubah saat pengguna
     * berpindah halaman atau mengganti penyaring, sehingga tidak perlu ikut
     * dikirim ulang di setiap permintaan.
     *
     * @return array<string, mixed>
     */
    private function opsiFilter(): array
    {
        return [
            'kategori' => Category::query()
                ->active()
                ->orderBy('nama')
                ->get(['id', 'nama']),

            // Dipakai mencari label chip filter yang sedang aktif — nama
            // induk disertakan supaya "Divisi Keuangan Internal" dapat
            // dibedakan dari divisi bernama mirip di deputi lain.
            'unit' => Unit::query()
                ->active()
                ->with('parent:id,nama')
                ->orderBy('nama')
                ->get(['id', 'nama', 'parent_id'])
                ->map(fn (Unit $unit): array => [
                    'id' => $unit->id,
                    'nama' => $unit->parent === null
                        ? $unit->nama
                        : "{$unit->parent->nama} — {$unit->nama}",
                ]),

            // Bentuk pohon untuk `UnitTreeSelect` — nama TIDAK digabung
            // dengan induknya di sini, komponennya sendiri yang menyusun
            // hierarkinya lewat `parent_id`.
            'unit_pohon' => Unit::query()
                ->active()
                ->orderBy('parent_id')
                ->orderBy('nama')
                ->get(['id', 'nama', 'parent_id']),
        ];
    }

    /**
     * @return array{dibagikan_ke_semua: bool, jenjang_jabatan: string, unit: list<array{id: int, nama: string}>, orang_tertentu: list<array{id: int, nama: string}>}
     */
    private function mekanismeAkses(Document $document, DocumentAccessChanges $perubahan): array
    {
        return [
            'dibagikan_ke_semua' => $document->is_shared_to_all,
            'jenjang_jabatan' => $document->min_tingkat_akses === null
                ? 'Tidak diatur'
                : "Tingkat {$document->min_tingkat_akses} ke atas",
            'unit' => $perubahan->unitDitambahkan,
            'orang_tertentu' => $perubahan->penggunaDitambahkan,
        ];
    }

    /** Mencatat setiap target yang betul-betul berubah, bukan seluruh daftar. */
    private function catatPerubahanAkses(
        ActivityLogService $aktivitas,
        Document $document,
        User $pelaku,
        DocumentAccessChanges $perubahan,
    ): void {
        foreach ($perubahan->unitDitambahkan as $unit) {
            $aktivitas->record(
                ActivityLogName::DocumentUnit,
                AuditEvent::AccessGranted,
                "Akses unit \"{$unit['nama']}\" ditambahkan.",
                $document,
                $pelaku,
                ['target' => $unit],
            );
        }

        foreach ($perubahan->unitDicabut as $unit) {
            $aktivitas->record(
                ActivityLogName::DocumentUnit,
                AuditEvent::AccessRevoked,
                "Akses unit \"{$unit['nama']}\" dicabut.",
                $document,
                $pelaku,
                ['target' => $unit],
            );
        }

        foreach ($perubahan->penggunaDitambahkan as $pengguna) {
            $aktivitas->record(
                ActivityLogName::DocumentShare,
                AuditEvent::AccessGranted,
                "Akses untuk \"{$pengguna['nama']}\" ditambahkan.",
                $document,
                $pelaku,
                ['target' => $pengguna],
            );
        }

        foreach ($perubahan->penggunaDicabut as $pengguna) {
            $aktivitas->record(
                ActivityLogName::DocumentShare,
                AuditEvent::AccessRevoked,
                "Akses untuk \"{$pengguna['nama']}\" dicabut.",
                $document,
                $pelaku,
                ['target' => $pengguna],
            );
        }
    }
}
