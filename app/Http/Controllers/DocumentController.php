<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\DocumentListData;
use App\Http\Requests\DocumentIndexRequest;
use App\Models\Category;
use App\Models\Document;
use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        );
    }

    /**
     * Menampilkan berkas di dalam peramban (FR-09b).
     *
     * Proteksinya IDENTIK dengan unduhan — satu-satunya perbedaan adalah header
     * `Content-Disposition`. Rute pratinjau yang lebih longgar akan menjadi
     * pintu belakang menuju berkas yang sama.
     */
    public function previewFile(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response(
            $document->file_path,
            $document->file_name_original,
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
            ->through(DocumentListData::fromModel(...));
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

            'unit' => Unit::query()
                ->active()
                ->with('parent:id,nama')
                ->orderBy('nama')
                ->get(['id', 'nama', 'parent_id'])
                ->map(fn (Unit $unit): array => [
                    'id' => $unit->id,
                    // Nama induk disertakan supaya "Divisi Keuangan Internal"
                    // dapat dibedakan dari divisi bernama mirip di deputi lain.
                    'nama' => $unit->parent === null
                        ? $unit->nama
                        : "{$unit->parent->nama} — {$unit->nama}",
                ]),
        ];
    }
}
