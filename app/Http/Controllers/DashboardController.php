<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\DashboardData;
use App\Data\DocumentListData;
use App\Data\KategoriRingkasData;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ringkasan dokumen untuk pengguna yang sedang masuk (FR-01 s.d. FR-05).
 *
 * Setiap angka dihitung dari `Document::visibleTo()`, sehingga dua akun berbeda
 * melihat dasbor yang berbeda. Tidak ada satu pun hitungan yang memakai jalan
 * pintas ke seluruh tabel.
 */
final class DashboardController extends Controller
{
    private const JUMLAH_TERBARU = 5;

    private const JUMLAH_EVALUASI = 5;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $rentang = $this->rentangEvaluasi($request);

        $rekap = $this->rekap($user, $rentang);

        return Inertia::render('Dashboard', [
            'data' => new DashboardData(
                total: (int) $rekap->total,
                berlaku: (int) $rekap->berlaku,
                kadaluarsa: (int) $rekap->kadaluarsa,
                jumlah_mendekati_evaluasi: (int) $rekap->mendekati_evaluasi,
                per_kategori: $this->perKategori($user),
                terbaru: $this->terbaru($user),
                mendekati_evaluasi: $this->mendekatiEvaluasi($user, $rentang),
                rentang_evaluasi: $rentang,
                rentang_pilihan: config('dms.dokumen.rentang_evaluasi_pilihan'),
            ),
        ]);
    }

    /**
     * Titik awal setiap hitungan: hanya dokumen aktif yang berhak dilihat.
     *
     * @return Builder<Document>
     */
    private function dasar(User $user): Builder
    {
        return Document::query()->visibleTo($user)->active();
    }

    /**
     * Empat angka statistik dalam satu query.
     *
     * Menghitungnya terpisah berarti menjalankan rantai OR hak akses yang sama
     * sebanyak empat kali — dan rantai itu memuat dua subquery `EXISTS`,
     * sehingga pengulangannya tidak murah.
     */
    private function rekap(User $user, int $rentang): object
    {
        return $this->dasar($user)
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(status = ?) as berlaku', [DocumentStatus::Berlaku->value])
            ->selectRaw('sum(status = ?) as kadaluarsa', [DocumentStatus::Kadaluarsa->value])
            ->selectRaw(
                'sum(status = ? and masa_berlaku is not null and masa_berlaku between ? and ?)'
                .' as mendekati_evaluasi',
                [
                    DocumentStatus::Berlaku->value,
                    now()->toDateString(),
                    now()->addDays($rentang)->toDateString(),
                ],
            )
            ->first();
    }

    /**
     * Jumlah dokumen per kategori (FR-02).
     *
     * Satu query dengan `groupBy`, bukan memutari daftar kategori lalu
     * memanggil `count()` di tiap perulangan — pola itu menghasilkan sepuluh
     * query untuk sepuluh kategori, dan bertambah seiring kategori baru.
     *
     * Kategori nonaktif tetap ditampilkan selama masih punya dokumen: yang
     * dinonaktifkan hanya kemunculannya sebagai pilihan baru, bukan datanya.
     *
     * @return list<KategoriRingkasData>
     */
    private function perKategori(User $user): array
    {
        return $this->dasar($user)
            // Nama kategori diambil lewat join, bukan query kedua. Untuk sebelas
            // kategori bedanya memang kecil, tapi polanya yang penting: nilai
            // yang dapat diambil sekalian tidak perlu ditanyakan terpisah.
            ->join('categories', 'categories.id', '=', 'documents.category_id')
            ->selectRaw('categories.id, categories.nama, count(*) as jumlah')
            ->groupBy('categories.id', 'categories.nama')
            ->orderBy('categories.nama')
            ->get()
            ->map(fn (object $baris) => new KategoriRingkasData(
                id: (int) $baris->id,
                nama: $baris->nama,
                jumlah: (int) $baris->jumlah,
            ))
            ->all();
    }

    /**
     * Dokumen terbaru yang dapat dilihat pengguna (FR-03).
     *
     * @return list<DocumentListData>
     */
    private function terbaru(User $user): array
    {
        return $this->daftarRingkas(
            $this->dasar($user)->latest('tanggal')->limit(self::JUMLAH_TERBARU),
        );
    }

    /**
     * Dokumen yang masa berlakunya jatuh dalam rentang terpilih (FR-04).
     *
     * @return list<DocumentListData>
     */
    private function mendekatiEvaluasi(User $user, int $rentang): array
    {
        return $this->daftarRingkas(
            $this->dasar($user)
                ->mendekatiMasaEvaluasi($rentang)
                ->orderBy('masa_berlaku')
                ->limit(self::JUMLAH_EVALUASI),
        );
    }

    /**
     * @param  Builder<Document>  $query
     * @return list<DocumentListData>
     */
    private function daftarRingkas(Builder $query): array
    {
        return $query
            // Kolom dibatasi eksplisit supaya `extracted_text` tidak pernah
            // ikut terambil — kolom `longText` yang bisa berukuran megabyte
            // per baris dan tidak pernah ditampilkan di ringkasan.
            ->select(Document::KOLOM_DAFTAR)
            // `targetUnits` dan `sharedUsers` sengaja TIDAK dimuat: dasbor
            // tidak menampilkan ringkasan mekanisme akses, sehingga memuatnya
            // berarti membayar dua query per daftar untuk data yang langsung
            // dibuang. Halaman daftar dokumen memuatnya karena memang
            // menampilkannya.
            ->with([
                'category:id,nama',
                'originUnit:id,nama',
                'uploader:id,name',
            ])
            ->get()
            ->map(DocumentListData::ringkas(...))
            ->all();
    }

    /**
     * Rentang hari untuk kartu masa evaluasi.
     *
     * Dipilih pengguna, bukan dipatok satu angka dari kode: berapa hari ke depan
     * yang perlu diwaspadai adalah kesepakatan operasional yang berbeda-beda
     * antar unit (`Catatan_Audit.md` isu #14). Nilai di luar daftar yang
     * tersedia diabaikan, supaya query string yang disunting sembarangan tidak
     * menghasilkan rentang yang tidak masuk akal.
     */
    private function rentangEvaluasi(Request $request): int
    {
        $pilihan = config('dms.dokumen.rentang_evaluasi_pilihan');
        $diminta = (int) $request->integer('rentang');

        return in_array($diminta, $pilihan, true)
            ? $diminta
            : config('dms.dokumen.rentang_evaluasi_awal');
    }
}
