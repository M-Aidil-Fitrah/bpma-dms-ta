<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\DocumentAccessChanges;
use App\Data\DocumentDetailData;
use App\Data\DocumentEditData;
use App\Data\DocumentListData;
use App\Data\DocumentVersionData;
use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use App\Http\Requests\DocumentIndexRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Jobs\ExtractDocumentTextJob;
use App\Jobs\GenerateDocumentThumbnailJob;
use App\Models\Category;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\User;
use App\Services\ActivityLogQuery;
use App\Services\ActivityLogService;
use App\Services\DocumentAccessWriter;
use App\Services\DocumentListingService;
use App\Services\DocumentMetadataChanges;
use App\Services\DocumentThumbnailService;
use App\Services\DocumentUploadService;
use App\Services\DocumentVersionService;
use App\Services\DocumentWorkspaceService;
use App\Support\BatasUnggah;
use App\Support\JenjangAkses;
use App\Support\PenyajianBerkas;
use App\Support\UnitOptions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Daftar dokumen — halaman inti aplikasi (FR-16 s.d. FR-22).
 *
 * Pencarian di sini masih terbatas judul dan nomor. Pencarian berbasis isi
 * dokumen lewat index FULLTEXT menyusul bersama modul pencarian lanjutan.
 */
final class DocumentController extends Controller
{
    public function index(DocumentIndexRequest $request, DocumentListingService $listing): Response
    {
        $this->authorize('viewAny', Document::class);

        return Inertia::render('Documents/Index', [
            'dokumen' => $this->daftar($request, $listing),
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
    public function create(Request $request): Response
    {
        $this->authorize('create', Document::class);

        $pengganti = null;
        if (request()->filled('replace')) {
            $pengganti = Document::with(['targetUnits:id,nama', 'sharedUsers:id,name,jabatan_id,unit_id', 'sharedUsers.jabatan:id,nama', 'sharedUsers.unit:id,nama'])
                ->findOrFail(request()->integer('replace'));
            $this->authorize('update', $pengganti);
            abort_unless($pengganti->is_active && $pengganti->replacementDocument()->doesntExist(), 422);
        }

        return Inertia::render('Documents/Create', [
            'opsi' => $this->opsiFormulir($request->user()),
            'pengganti' => $pengganti === null ? null : DocumentEditData::fromModel($pengganti),
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
        DocumentVersionService $versi,
        ActivityLogService $aktivitas,
    ): RedirectResponse {
        $this->authorize('create', Document::class);

        $lama = $request->filled('replaces_document_id')
            ? Document::query()->findOrFail($request->integer('replaces_document_id'))
            : null;
        if ($lama !== null) {
            $this->authorize('update', $lama);
            abort_unless($lama->is_active && $lama->replacementDocument()->doesntExist(), 422);
        }

        $berkas = $uploader->store($request->file('file'));
        $kolomDokumen = $request->kolomDokumen();

        // Pengguna yang ditempatkan pada unit selalu menerbitkan dokumen dari
        // unitnya. Nilai dari browser tidak dipercaya karena permintaan dapat
        // dikirim langsung tanpa melewati dropdown yang dikunci.
        if ($lama === null && $request->user()->unit_id !== null) {
            $kolomDokumen['origin_unit_id'] = $request->user()->unit_id;
        }

        try {
            $document = $lama === null
                ? DB::transaction(function () use ($request, $berkas, $kolomDokumen, $akses, $aktivitas): Document {
                    $document = Document::create([
                        ...$kolomDokumen,
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
                })
                : $this->simpanVersiMajor($lama, $request, $berkas, $versi, $aktivitas);
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

        if (app(DocumentThumbnailService::class)->didukung($document->file_mime_type)) {
            // Antrean terpisah dari ekstraksi teks (`default`) — OCR PDF
            // pindaian bisa memakan waktu 15 menit; tanpa pemisahan ini,
            // gambar mini dokumen lain ikut tertahan di belakang satu OCR
            // yang sedang berjalan pada worker yang sama.
            GenerateDocumentThumbnailJob::dispatch($document)->onQueue('thumbnail');
        }

        return redirect()
            ->route('documents.show', $document)
            ->with('success', $lama === null ? 'Dokumen berhasil diunggah.' : 'Versi baru dokumen berhasil dibuat.');
    }

    /**
     * Formulir ubah dokumen (FR-08, FR-42).
     *
     * Nilai akses yang sedang berlaku ikut dikirim supaya formulir terbuka
     * dengan keadaan sekarang, bukan kosong. Formulir yang kosong akan membuat
     * penyunting yang hanya ingin memperbaiki satu huruf pada judul tanpa sadar
     * mencabut seluruh daftar aksesnya.
     */
    public function edit(Request $request, Document $document): Response
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
            'opsi' => $this->opsiFormulir($request->user()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $berkas
     */
    private function simpanVersiMajor(
        Document $lama,
        StoreDocumentRequest $request,
        array $berkas,
        DocumentVersionService $versi,
        ActivityLogService $aktivitas,
    ): Document {
        if ($lama->file_mime_type !== $berkas['file_mime_type']) {
            throw ValidationException::withMessages([
                'file' => 'Versi baru wajib memakai format berkas yang sama dengan versi sebelumnya.',
            ]);
        }

        $kolomDokumen = $request->kolomDokumen();
        // Versi baru adalah kelanjutan arsip, sehingga unit kerjanya tidak
        // boleh berubah hanya karena pengunggah versi berbeda dari pemiliknya.
        $kolomDokumen['origin_unit_id'] = $lama->origin_unit_id;

        $document = $versi->buatMajor(
            $lama,
            [...$kolomDokumen, 'status' => DocumentStatus::Berlaku],
            $berkas,
            $request->unitIds(),
            $request->penerimaIds(),
            $request->user(),
            $request->catatanVersi(),
        );

        $aktivitas->record(
            ActivityLogName::Dokumen,
            AuditEvent::DocumentReplaced,
            'Dokumen digantikan oleh versi major baru.',
            $lama,
            $request->user(),
            ['replacement_document_id' => $document->id],
        );
        $aktivitas->record(
            ActivityLogName::Dokumen,
            AuditEvent::DocumentReplaced,
            'Versi major dokumen dibuat.',
            $document,
            $request->user(),
            ['replaces_document_id' => $lama->id, 'version_note' => $document->version_note],
        );

        return $document;
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
        DocumentVersionService $versi,
        ActivityLogService $aktivitas,
    ): RedirectResponse {
        $this->authorize('update', $document);

        $kolomDokumen = $request->kolomDokumen();
        if ($request->user()->unit_id !== null) {
            // Pengguna berunit tidak dapat memindahkan kepemilikan arsip lewat
            // request buatan sendiri; pimpinan dan Superadmin tetap dapat
            // melakukannya lewat revisi metadata yang tercatat.
            $kolomDokumen['origin_unit_id'] = $document->origin_unit_id;
        }

        $snapshotDenganPerubahan = clone $document;
        $snapshotDenganPerubahan->fill($kolomDokumen);
        $perubahanMetadata = $metadata->fromDirty($snapshotDenganPerubahan);

        $revisi = $versi->buatMinor(
            $document,
            $kolomDokumen,
            $request->unitIds(),
            $request->penerimaIds(),
            $request->user(),
            $request->catatanVersi(),
        );
        $perubahanAkses = $akses->perubahanAntar($document, $revisi);
        $aktivitas->record(
            ActivityLogName::Dokumen,
            AuditEvent::DocumentUpdated,
            'Revisi metadata dokumen dibuat.',
            $revisi,
            $request->user(),
            ['replaces_document_id' => $document->id, 'version_note' => $revisi->version_note],
            $perubahanMetadata['before'],
            $perubahanMetadata['after'],
        );
        $this->catatPerubahanAkses($aktivitas, $revisi, $request->user(), $perubahanAkses);

        return redirect()
            ->route('documents.show', $revisi)
            ->with('success', 'Revisi metadata dokumen berhasil dibuat.');
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
        $this->authorize('trash', $document);

        app(DocumentWorkspaceService::class)->trashDocument($document, $request->user());

        DB::transaction(function () use ($document, $request, $aktivitas): void {
            $aktivitas->record(
                ActivityLogName::Dokumen,
                AuditEvent::DocumentTrashed,
                'Dokumen dipindahkan ke Sampah.',
                $document,
                $request->user(),
            );
        });

        return redirect()
            ->route('documents.trash')
            ->with('success', "Dokumen \"{$document->judul}\" dipindahkan ke Sampah selama 30 hari.");
    }

    public function restoreTrash(Request $request, Document $document, DocumentWorkspaceService $workspace, ActivityLogService $aktivitas): RedirectResponse
    {
        $this->authorize('restoreTrash', $document);
        abort_if($document->trashed_at === null, 422);
        $workspace->restoreDocument($document);

        $aktivitas->record(
            ActivityLogName::Dokumen,
            AuditEvent::DocumentTrashRestored,
            'Dokumen dipulihkan dari Sampah.',
            $document,
            $request->user(),
        );

        return redirect()->route('documents.show', $document)->with('success', 'Dokumen berhasil dipulihkan dari Sampah.');
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

    /** Membuat major terbaru dari snapshot versi lama milik pemilik rantai. */
    public function restoreVersion(
        Request $request,
        Document $document,
        DocumentVersionService $versi,
        ActivityLogService $aktivitas,
    ): RedirectResponse {
        $this->authorize('restoreVersion', $document);

        $data = $request->validate([
            'version_note' => ['required', 'string', 'max:500'],
        ]);
        $revisi = $versi->pulihkan($document, $request->user(), trim($data['version_note']));

        $aktivitas->record(
            ActivityLogName::Dokumen,
            AuditEvent::DocumentVersionRestored,
            'Versi arsip dijadikan versi terbaru.',
            $document,
            $request->user(),
            ['restored_as_document_id' => $revisi->id],
        );
        $aktivitas->record(
            ActivityLogName::Dokumen,
            AuditEvent::DocumentVersionRestored,
            'Versi terbaru dibuat dari arsip.',
            $revisi,
            $request->user(),
            ['restores_document_id' => $document->id, 'version_note' => $revisi->version_note],
        );

        return redirect()
            ->route('documents.show', $revisi)
            ->with('success', 'Versi arsip berhasil dijadikan versi terbaru.');
    }

    /**
     * Halaman detail satu dokumen (FR-07).
     *
     * Berbeda dari daftar, di sini `extracted_text` justru dimuat — panel teks
     * pratinjau untuk berkas non-visual dibangun dari kolom itu. Batasan yang
     * berlaku di halaman daftar tidak berlaku di sini karena yang diambil hanya
     * satu baris, bukan dua puluh.
     */
    public function show(
        Request $request,
        Document $document,
        ActivityLogQuery $aktivitas,
        DocumentWorkspaceService $workspace,
    ): Response {
        $this->authorize('view', $document);
        $workspace->recordRecent($document, $request->user());

        // Relasi identitas tunggal ini dibaca sekaligus. Memanggil `load()`
        // untuk kategori, unit asal, pengunggah, jabatan, dan unit pengunggah
        // akan berubah menjadi lima query meski halaman hanya membuka satu
        // dokumen. Relasi koleksi tetap memakai eager load agar modelnya utuh.
        $document = Document::query()
            ->select('documents.*')
            ->addSelect([
                'document_category.nama as kategori_nama',
                'origin_unit.nama as unit_asal_nama',
                'document_uploader.name as pengunggah_nama',
                'uploader_jabatan.nama as jabatan_pengunggah_nama',
                'uploader_unit.nama as unit_pengunggah_nama',
            ])
            ->leftJoin('categories as document_category', 'document_category.id', '=', 'documents.category_id')
            ->leftJoin('units as origin_unit', 'origin_unit.id', '=', 'documents.origin_unit_id')
            ->leftJoin('users as document_uploader', 'document_uploader.id', '=', 'documents.uploaded_by')
            ->leftJoin('jabatans as uploader_jabatan', 'uploader_jabatan.id', '=', 'document_uploader.jabatan_id')
            ->leftJoin('units as uploader_unit', 'uploader_unit.id', '=', 'document_uploader.unit_id')
            ->findOrFail($document->id);

        $document->load([
            'targetUnits:id,nama',
            'sharedUsers:id,name,unit_id',
            'sharedUsers.unit:id,nama',
            'replacedDocument:id,nomor,judul',
            // Foreign key wajib ikut dipilih pada hasOne; tanpa ini Eloquent
            // tidak dapat memasangkan versi penerus ke dokumen yang dibuka.
            'replacementDocument:id,replaces_document_id,nomor,judul',
        ]);
        $akarId = $document->version_root_id ?? $document->id;
        // `visibleTo()` juga diterapkan di sini, bukan cuma pada dokumen yang
        // sedang dibuka: setiap versi bisa punya mekanisme aksesnya sendiri
        // (FR-42), sehingga versi lama yang sengaja dibuat "Hanya saya" oleh
        // pengunggahnya tidak boleh ikut membocorkan nama berkas atau catatan
        // revisinya ke orang lain yang kebetulan berhak atas versi terbaru.
        $versi = Document::query()
            ->where('version_root_id', $akarId)
            ->visibleTo($request->user())
            ->with('uploader:id,name')
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->get();
        $latestId = $versi->first()?->id ?? $document->id;
        $jabatanTujuan = $document->min_tingkat_akses === null
            ? []
            : Jabatan::query()
                ->active()
                ->where('tingkat_akses', '<=', $document->min_tingkat_akses)
                ->orderBy('tingkat_akses')
                ->orderBy('nama')
                ->pluck('nama')
                ->all();

        return Inertia::render('Documents/Show', [
            'dokumen' => DocumentDetailData::fromModel(
                $document,
                bolehUbah: $request->user()->can('update', $document),
                bolehPindahKeSampah: $request->user()->can('trash', $document),
                bolehAktifkan: $request->user()->can('restore', $document),
                bolehPulihkanVersi: $request->user()->can('restoreVersion', $document),
                jabatanTujuan: $jabatanTujuan,
            ),
            'versi' => $versi
                ->map(fn (Document $versi): DocumentVersionData => DocumentVersionData::fromModel($versi, $document->id, $latestId))
                ->all(),
            'riwayat' => $aktivitas->forDocument($document),
            // Dikirim dari config, bukan di-hardcode di hook React — anggaran
            // polling harus selalu cukup menutupi durasi OCR terpanjang yang
            // mungkin terjadi (`pdf_ocr_timeout_detik`), dan satu-satunya cara
            // menjamin itu tanpa dua angka yang bisa diam-diam menyimpang
            // adalah membaca sumber yang sama.
            'pollingKonfigurasi' => [
                'jedaMs' => (int) config('dms.ekstraksi.polling_jeda_ms'),
                'maksPercobaan' => (int) config('dms.ekstraksi.polling_maks_percobaan'),
            ],
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
    public function serveFile(Request $request, Document $document, ActivityLogService $aktivitas): BinaryFileResponse
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

        // Tipe berkas tidak diteruskan apa adanya bahkan pada unduhan —
        // `Content-Disposition: attachment` memang sudah menyuruh peramban
        // menyimpan, tapi tidak semua peramban lama patuh, dan tipe generik
        // menutup sisa celahnya.
        return PenyajianBerkas::respons(
            Storage::disk('local')->path($document->file_path),
            $document->file_name_original,
            $document->file_mime_type,
            'attachment',
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
    public function previewFile(Request $request, Document $document, ActivityLogService $aktivitas): BinaryFileResponse
    {
        $this->authorize('view', $document);

        $memakaiPreview = $document->preview_path !== null
            && Storage::disk('local')->exists($document->preview_path);
        $path = $memakaiPreview ? $document->preview_path : $document->file_path;
        $mime = $memakaiPreview ? 'application/pdf' : $document->file_mime_type;
        $nama = ! $memakaiPreview
            ? $document->file_name_original
            : pathinfo($document->file_name_original, PATHINFO_FILENAME).'.pdf';

        abort_unless(Storage::disk('local')->exists($path), 404);

        if (! $memakaiPreview && ! PenyajianBerkas::bolehInline($mime)) {
            return $this->serveFile($request, $document, $aktivitas);
        }

        // Tipe diambil dari daftar-boleh, bukan dari kolom apa adanya, supaya
        // nilai yang aneh di basis data tidak ikut diteruskan mentah-mentah
        // ke peramban.
        return PenyajianBerkas::respons(Storage::disk('local')->path($path), $nama, $mime, 'inline');
    }

    /**
     * Menyajikan turunan JPG yang dibuat server untuk kartu grid.
     *
     * Gambar mini tetap dokumen turunan yang sensitif: ia tidak boleh diberi
     * URL penyimpanan langsung atau proteksi yang lebih longgar dari berkas
     * aslinya.
     */
    public function thumbnail(Document $document): BinaryFileResponse
    {
        $this->authorize('view', $document);

        abort_unless($document->thumbnail_path !== null, 404);
        abort_unless(Storage::disk('local')->exists($document->thumbnail_path), 404);

        return PenyajianBerkas::respons(
            Storage::disk('local')->path($document->thumbnail_path),
            "thumbnail-{$document->id}.jpg",
            'image/jpeg',
            'inline',
        );
    }

    /**
     * @return LengthAwarePaginator<int, DocumentListData>
     */
    private function daftar(DocumentIndexRequest $request, DocumentListingService $listing): LengthAwarePaginator
    {
        return $listing->paginasi(
            Document::query()->visibleTo($request->user())->active(),
            $request,
            $request->user(),
        );
    }

    /**
     * Pilihan yang mengisi formulir unggah.
     *
     * @return array<string, mixed>
     */
    private function opsiFormulir(User $user): array
    {
        return [
            ...$this->opsiFilter(),

            // Unit kerja adalah konteks arsip, bukan mekanisme akses. Untuk
            // pengguna yang ditempatkan di unit tertentu, nilainya selalu
            // mengikuti unit akun saat dokumen dibuat; pimpinan tanpa unit
            // dapat menerbitkan sebagai Pimpinan BPMA, sedangkan Superadmin
            // wajib menyebut unit yang benar-benar menerbitkan dokumen.
            'unit_akun_id' => $user->unit_id,
            'unit_akun_nama' => $user->unit?->nama
                ?? ($user->isPimpinanTertinggi() ? 'Pimpinan BPMA' : null),
            'unit_kerja_wajib' => $user->isSuperadmin(),

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
                // "Lainnya" adalah pilihan cadangan, bukan kategori utama.
                // Tetap letakkan terakhir meski Superadmin menambah kategori.
                ->orderByRaw('CASE WHEN nama = ? THEN 1 ELSE 0 END', ['Lainnya'])
                ->orderBy('nama')
                ->get(['id', 'nama']),

            'unit' => UnitOptions::berlabel(),
            'unit_pohon' => UnitOptions::pohon(),
            'pengunggah' => User::query()->active()->orderBy('name')->get(['id', 'name']),
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
