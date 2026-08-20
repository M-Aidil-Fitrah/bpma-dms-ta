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
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogQuery;
use App\Services\ActivityLogService;
use App\Services\DocumentAccessWriter;
use App\Services\DocumentMetadataChanges;
use App\Services\DocumentThumbnailService;
use App\Services\DocumentUploadService;
use App\Services\DocumentVersionService;
use App\Services\PengaturanService;
use App\Support\BatasUnggah;
use App\Support\JenjangAkses;
use App\Support\PenyajianBerkas;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
    ): Response {
        $this->authorize('view', $document);

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
        $versi = Document::query()
            ->where('version_root_id', $akarId)
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
    private function daftar(DocumentIndexRequest $request, PengaturanService $pengaturan): LengthAwarePaginator
    {
        $user = $request->user();
        $kata = trim($request->string('cari')->toString());

        $query = Document::query()
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
            ->when($request->string('status_ekstraksi')->toString(), fn ($query, string $status) => $query->where('documents.extraction_status', $status))
            ->when($request->integer('pengunggah'), fn ($query, int $id) => $query->where('documents.uploaded_by', $id))
            ->when($request->string('tipe')->toString(), function ($query, string $tipe): void {
                match ($tipe) {
                    'pdf' => $query->where('documents.file_mime_type', 'application/pdf'),
                    'gambar' => $query->where('documents.file_mime_type', 'like', 'image/%'),
                    'word' => $query->where('documents.file_mime_type', 'like', '%wordprocessingml%'),
                    'teks' => $query->where('documents.file_mime_type', 'text/plain'),
                    default => $query->whereNotIn('documents.file_mime_type', ['application/pdf', 'text/plain'])
                        ->where('documents.file_mime_type', 'not like', 'image/%')
                        ->where('documents.file_mime_type', 'not like', '%wordprocessingml%'),
                };
            })
            ->when(
                $request->string('dari')->toString(),
                fn ($query, string $tanggal) => $query->whereDate('documents.tanggal', '>=', $tanggal),
            )
            ->when(
                $request->string('sampai')->toString(),
                fn ($query, string $tanggal) => $query->whereDate('documents.tanggal', '<=', $tanggal),
            );

        $pencarianDenganRelevansi = false;
        if ($kata !== '') {
            $query->where(fn ($pencarian) => $this->terapkanPencarian($pencarian, $kata));
            $pencarianDenganRelevansi = $this->tambahkanKonteksPencarian($query, $kata);
        }

        if ($pencarianDenganRelevansi && ! $request->boolean('urut_manual')) {
            $query->orderByDesc('search_field_priority')
                ->orderByDesc('search_relevance');
        } else {
            $query->orderBy($request->kolomUrutan(), $request->arahUrutan());
        }

        return $query
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
        $nomor = preg_replace('/[^a-z0-9]+/i', '', strtolower($kata)) ?? '';
        if ($this->adalahPencarianNomor($kata, $nomor)) {
            $query->where('documents.nomor_normalized', 'like', $nomor.'%');

            return;
        }

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
            $this->kueriBoolean($kata),
            ['mode' => 'boolean'],
        );
    }

    /**
     * Menambahkan metadata hasil pencarian tanpa memilih `extracted_text`.
     *
     * Projection ini dihitung oleh SQL hanya untuk baris halaman aktif.
     * Cuplikannya maksimum 220 karakter, sehingga daftar dapat menjelaskan
     * "ditemukan di mana" tanpa menjadikan endpoint daftar sebagai API isi
     * dokumen penuh.
     *
     * @param  Builder<Document>  $query
     */
    private function tambahkanKonteksPencarian(Builder $query, string $kata): bool
    {
        $kata = trim($kata);
        $nomor = preg_replace('/[^a-z0-9]+/i', '', strtolower($kata)) ?? '';

        if ($this->adalahPencarianNomor($kata, $nomor)) {
            $query->selectRaw('1 AS search_matches_nomor')
                ->selectRaw('0 AS search_matches_judul')
                ->selectRaw('0 AS search_matches_deskripsi')
                ->selectRaw('0 AS search_matches_isi');

            return false;
        }

        $frasa = mb_strtolower($kata);
        $pola = '%'.$this->escapeLike($frasa).'%';
        $nomorDalamFrasa = $this->nomorDiDalamFrasa($kata);
        $query->selectRaw('CASE WHEN LOWER(documents.nomor) LIKE ? THEN 1 ELSE 0 END AS search_matches_nomor', [$pola])
            ->selectRaw('CASE WHEN LOWER(documents.judul) LIKE ? THEN 1 ELSE 0 END AS search_matches_judul', [$pola])
            ->selectRaw('CASE WHEN LOWER(COALESCE(documents.deskripsi, \'\')) LIKE ? THEN 1 ELSE 0 END AS search_matches_deskripsi', [$pola]);

        if (mb_strlen($kata) < 3) {
            $query->selectRaw('0 AS search_matches_isi');

            return false;
        }

        $kataCuplikan = $this->kataCuplikan($frasa);
        if ($kataCuplikan === '') {
            $query->selectRaw('0 AS search_matches_isi');

            return false;
        }

        $query->selectRaw(
            'MATCH(documents.nomor, documents.judul, documents.deskripsi, documents.extracted_text) AGAINST (? IN BOOLEAN MODE) AS search_relevance',
            [$this->kueriBoolean($kata)],
        )
            ->selectRaw(
                'CASE
                    WHEN LOWER(documents.judul) LIKE ? THEN 600
                    WHEN LOWER(documents.judul) LIKE ? THEN 500
                    WHEN ? <> \'\' AND documents.nomor_normalized LIKE ? THEN 400
                    WHEN LOWER(COALESCE(documents.deskripsi, \'\')) LIKE ? THEN 300
                    ELSE 0
                END AS search_field_priority',
                [
                    $pola,
                    '%'.$this->escapeLike($this->kataCuplikan($frasa)).'%',
                    $nomorDalamFrasa,
                    '%'.$this->escapeLike($nomorDalamFrasa).'%',
                    $pola,
                ],
            )
            ->selectRaw(
                'CASE WHEN LOCATE(?, LOWER(COALESCE(documents.extracted_text, \'\'))) > 0 THEN 1 ELSE 0 END AS search_matches_isi',
                [$kataCuplikan],
            )
            ->selectRaw(
                'CASE WHEN LOCATE(?, LOWER(COALESCE(documents.extracted_text, \'\'))) > 0 THEN SUBSTRING(documents.extracted_text, GREATEST(1, LOCATE(?, LOWER(COALESCE(documents.extracted_text, \'\'))) - 80), 220) END AS search_excerpt',
                [$kataCuplikan, $kataCuplikan],
            )
            ->selectRaw(
                'CASE WHEN LOCATE(?, LOWER(COALESCE(documents.extracted_text, \'\'))) > 0 THEN (CHAR_LENGTH(LOWER(documents.extracted_text)) - CHAR_LENGTH(REPLACE(LOWER(documents.extracted_text), ?, \'\'))) / NULLIF(CHAR_LENGTH(?), 0) ELSE 0 END AS search_phrase_count',
                [$frasa, $frasa, $frasa],
            );

        return true;
    }

    private function adalahPencarianNomor(string $kata, string $nomor): bool
    {
        // Hanya nomor dokumen MURNI yang memakai jalur prefix terindeks.
        // "notulen 002/BPMA" adalah pencarian campuran, bukan nomor, dan
        // wajib tetap mencari judul + nomor melalui FULLTEXT.
        return $nomor !== ''
            && preg_match('/^(?=.*\\d)[a-z0-9]+(?:[\\/-][a-z0-9]+)+$/i', $kata) === 1;
    }

    private function kueriBoolean(string $kata): string
    {
        $istilah = $this->istilahPencarian($kata);

        return $istilah === [] ? $kata : implode(' ', array_map(fn (string $istilah): string => "+{$istilah}", $istilah));
    }

    /** @return list<string> */
    private function istilahPencarian(string $kata): array
    {
        preg_match_all('/[\\p{L}\\p{N}]{3,}/u', mb_strtolower($kata), $hasil);

        return array_values(array_unique($hasil[0]));
    }

    private function nomorDiDalamFrasa(string $kata): string
    {
        if (preg_match('/\\b\\d{2,}(?:[\\/-][[:alnum:]]+)+/iu', $kata, $hasil) !== 1) {
            return '';
        }

        return preg_replace('/[^a-z0-9]+/i', '', strtolower($hasil[0])) ?? '';
    }

    private function escapeLike(string $nilai): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $nilai);
    }

    private function kataCuplikan(string $kata): string
    {
        return $this->istilahPencarian($kata)[0] ?? '';
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
            'unit_akun_nama' => $user->unit?->nama,
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

            // Dipakai mencari label chip filter yang sedang aktif — nama
            // induk disertakan supaya "Divisi Keuangan Internal" dapat
            // dibedakan dari divisi bernama mirip di deputi lain.
            'unit' => $this->unitAktifTerurut()
                ->map(fn (Unit $unit): array => [
                    'id' => $unit->id,
                    'nama' => $unit->parent === null
                        ? $unit->nama
                        : "{$unit->parent->nama} — {$unit->nama}",
                ]),

            // Bentuk pohon untuk `UnitTreeSelect` — nama TIDAK digabung
            // dengan induknya di sini, komponennya sendiri yang menyusun
            // hierarkinya lewat `parent_id`.
            'unit_pohon' => $this->unitAktifTerurut()
                ->map(fn (Unit $unit): array => [
                    'id' => $unit->id,
                    'nama' => $unit->nama,
                    'parent_id' => $unit->parent_id,
                ]),
            'pengunggah' => User::query()->active()->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Unit tingkat atas selalu segera diikuti divisinya. Mengurutkan seluruh
     * nama secara datar membuat Sekretaris atau Deputi muncul jauh dari
     * divisinya sendiri dan mudah salah pilih pada dropdown.
     *
     * @return Collection<int, Unit>
     */
    private function unitAktifTerurut(): Collection
    {
        $unit = Unit::query()
            ->active()
            ->with('parent:id,nama')
            ->get(['id', 'nama', 'parent_id', 'tipe']);
        $anakPerInduk = $unit->whereNotNull('parent_id')->groupBy('parent_id');

        return $unit
            ->whereNull('parent_id')
            ->sortBy(fn (Unit $induk): string => ($induk->tipe === Unit::TIPE_SEKRETARIS ? '0' : '1').$induk->nama)
            ->flatMap(function (Unit $induk) use ($anakPerInduk) {
                return collect([$induk])->concat(
                    $anakPerInduk->get($induk->id, collect())->sortBy('nama'),
                );
            })
            ->values();
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
