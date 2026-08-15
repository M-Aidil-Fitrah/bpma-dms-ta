<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\DocumentDetailData;
use App\Data\DocumentEditData;
use App\Data\DocumentListData;
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
use App\Services\DocumentAccessWriter;
use App\Services\DocumentUploadService;
use App\Support\BatasUnggah;
use App\Support\JenjangAkses;
use App\Support\PenyajianBerkas;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
    public function index(DocumentIndexRequest $request): Response
    {
        $this->authorize('viewAny', Document::class);

        return Inertia::render('Documents/Index', [
            'dokumen' => $this->daftar($request),
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
    ): RedirectResponse {
        $this->authorize('create', Document::class);

        $berkas = $uploader->store($request->file('file'));

        try {
            $document = DB::transaction(function () use ($request, $berkas, $akses): Document {
                $document = Document::create([
                    ...$request->kolomDokumen(),
                    ...$berkas,
                    'status' => DocumentStatus::Berlaku,
                    'uploaded_by' => $request->user()->id,
                    'is_active' => true,
                ]);

                $akses->sinkron(
                    $document,
                    $request->unitIds(),
                    $request->penerimaIds(),
                    $request->user(),
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
    ): RedirectResponse {
        $this->authorize('update', $document);

        DB::transaction(function () use ($request, $document, $akses): void {
            $document->update($request->kolomDokumen());

            $akses->sinkron(
                $document,
                $request->unitIds(),
                $request->penerimaIds(),
                $request->user(),
            );
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
    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $document->update(['is_active' => false]);

        return redirect()
            ->route('documents.index')
            ->with('success', "Dokumen \"{$document->judul}\" dinonaktifkan dan tidak lagi tampil di daftar.");
    }

    /**
     * Mengaktifkan kembali dokumen yang dinonaktifkan (FR-10).
     *
     * Hanya Superadmin — lihat alasannya di `DocumentPolicy::restore()`.
     */
    public function restore(Document $document): RedirectResponse
    {
        $this->authorize('restore', $document);

        $document->update(['is_active' => true]);

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
    public function show(Request $request, Document $document): Response
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
    public function serveFile(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

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
    public function previewFile(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        if (! PenyajianBerkas::bolehInline($document->file_mime_type)) {
            return $this->serveFile($document);
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
    private function daftar(DocumentIndexRequest $request): LengthAwarePaginator
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
                fn ($query, string $kata) => $query->where(
                    fn ($q) => $q
                        ->where('documents.judul', 'like', "%{$kata}%")
                        ->orWhere('documents.nomor', 'like', "%{$kata}%"),
                ),
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
            ->paginate(config('dms.dokumen.per_halaman'))
            ->withQueryString()
            ->through(fn (Document $document): DocumentListData => DocumentListData::fromModel($document, $user));
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
}
