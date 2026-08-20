<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DocumentEditScope;
use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use App\Models\Category;
use App\Models\Document;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\Data\DokumenKurasi;
use Database\Seeders\Support\BerkasContoh;
use Database\Seeders\Support\NomorDokumen;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Dua ratus dua puluh dokumen beserta berkas fisiknya.
 *
 * Empat puluh lima disusun satu per satu (`DokumenKurasi`), sisanya
 * dibangkitkan dari gabungan potongan judul yang tetap terbaca wajar.
 *
 * Sebaran mekanisme aksesnya dirancang, bukan diacak: setiap cabang
 * `visibleTo()` harus punya bahan uji yang cukup di FEAT-05, termasuk dokumen
 * yang tidak dapat dilihat siapa pun kecuali pengunggahnya — tanpa kontrol
 * negatif semacam itu, sistem akses yang selalu menjawab "boleh" akan terlihat
 * lolos seluruh pengujian.
 */
final class DocumentSeeder extends Seeder
{
    /** Benih tetap supaya hasilnya sama di setiap laptop. */
    private const BENIH = 20260814;

    private const JUMLAH_TOTAL = 220;

    /**
     * Sebaran kombinasi mekanisme akses untuk dokumen di luar skenario demo.
     *
     * @var array<string, int>
     */
    private const SEBARAN_AKSES = [
        'semua' => 35,          // is_shared_to_all saja
        'satu_unit' => 56,      // satu unit saja
        'unit_bertingkat' => 25, // unit induk beserta divisi di bawahnya
        'jenjang' => 30,        // min_tingkat_akses saja
        'orang' => 25,          // document_shares saja
        'dua' => 30,            // dua mekanisme sekaligus
        'tiga' => 11,           // tiga mekanisme sekaligus
        'pengunggah' => 3,      // tanpa mekanisme apa pun — kontrol negatif
    ];

    private NomorDokumen $nomor;

    /** @var Collection<int, Unit> */
    private Collection $units;

    /** @var Collection<int, User> */
    private Collection $pengguna;

    /** @var array<string, int> */
    private array $kategoriIds;

    public function run(): void
    {
        // Seeder referensi boleh dijalankan ulang karena memakai
        // `updateOrCreate()`. Dokumen berbeda: data kerja nyata tidak boleh
        // dihapus atau ditumpuk hanya karena seseorang menjalankan `db:seed`.
        // Keadaan demo lengkap dibuat lewat database kosong
        // (`migrate:fresh --seed`), sedangkan basis data yang sudah memiliki
        // dokumen dipertahankan apa adanya.
        if (Document::query()->exists()) {
            $this->command?->warn('Seeder dokumen dilewati karena basis data sudah berisi dokumen.');

            return;
        }

        mt_srand(self::BENIH);

        BerkasContoh::bersihkanPenyimpananSeed();

        $this->nomor = new NomorDokumen;
        $this->units = Unit::with('parent')->get();
        $this->pengguna = User::whereNotNull('jabatan_id')->get();
        $this->kategoriIds = Category::pluck('id', 'nama')->all();

        $this->seedSkenarioDemo();
        $this->seedKurasi();
        $this->seedBertemplate();
    }

    /**
     * Lima dokumen dengan kombinasi akses yang ditetapkan eksplisit.
     *
     * Matriks pengujian otorisasi (`PRD.md` §4.2) bergantung pada nilai-nilai
     * ini, jadi tidak boleh dibagikan otomatis seperti dokumen lain.
     */
    private function seedSkenarioDemo(): void
    {
        $mstiId = $this->unitId('Divisi Manajemen Sistem Teknologi Informasi');
        $ddbId = $this->unitId('Deputi Dukungan Bisnis');
        $maya = $this->penggunaByEmail('maya.puspita@bpma.internal');
        $dedi = $this->penggunaByEmail('dedi.kurniawan@bpma.internal');

        $akses = [
            // Bagikan ke semua.
            ['is_shared_to_all' => true],
            // Satu unit.
            ['units' => [$mstiId]],
            // Unit induk beserta seluruh divisi di bawahnya (cascade).
            ['units' => $this->unitBesertaTurunan($ddbId)],
            // Jenjang jabatan.
            ['min_tingkat_akses' => 2],
            // Tiga mekanisme sekaligus — pembeda utama produk ini.
            ['units' => [$mstiId], 'min_tingkat_akses' => 2, 'shares' => [$maya->id]],
        ];

        foreach (DokumenKurasi::DEMO as $i => $data) {
            $this->buatDokumen($data, $akses[$i], $dedi, urutan: $i);
        }
    }

    private function seedKurasi(): void
    {
        $rencana = $this->rencanaAkses(count(DokumenKurasi::UMUM));

        foreach (DokumenKurasi::UMUM as $i => $data) {
            $this->buatDokumen($data, $this->aksesDari($rencana[$i]), urutan: $i + 5);
        }
    }

    private function seedBertemplate(): void
    {
        $sisa = self::JUMLAH_TOTAL - count(DokumenKurasi::DEMO) - count(DokumenKurasi::UMUM);
        $rencana = $this->rencanaAkses($sisa, lanjutanDari: count(DokumenKurasi::UMUM));
        $berkas = array_keys(BerkasContoh::KATALOG);
        $namaKategori = array_keys($this->kategoriIds);

        for ($i = 0; $i < $sisa; $i++) {
            $unit = $this->units->random();

            $this->buatDokumen([
                'judul' => $this->judulBertemplate(),
                'kategori' => $namaKategori[$i % count($namaKategori)],
                'unit' => $unit->nama,
                'berkas' => $berkas[$i % count($berkas)],
            ], $this->aksesDari($rencana[$i]), urutan: $i + 45);
        }
    }

    /**
     * @param  array{judul: string, kategori: string, unit: string, berkas: string}  $data
     * @param  array{is_shared_to_all?: bool, min_tingkat_akses?: int, units?: list<int>, shares?: list<int>}  $akses
     */
    private function buatDokumen(
        array $data,
        array $akses,
        ?User $pengunggah = null,
        int $urutan = 0,
    ): void {
        $unit = $this->units->firstWhere('nama', $data['unit']);
        $tanggal = $this->tanggalAcak();
        $berkas = BerkasContoh::salin(
            $data['berkas'],
            date('Y', strtotime($tanggal)),
            date('m', strtotime($tanggal)),
        );

        $status = BerkasContoh::KATALOG[$data['berkas']]['status'];
        $teksEkstraksi = BerkasContoh::teksEkstraksi($data['berkas']);

        $document = Document::create([
            'nomor' => $this->nomor->berikutnya($unit, $tanggal),
            'judul' => $data['judul'],
            'category_id' => $this->kategoriIds[$data['kategori']],
            'origin_unit_id' => $unit->id,
            'tanggal' => $tanggal,
            'masa_berlaku' => $this->masaBerlaku($tanggal, $urutan),
            'status' => DocumentStatus::Berlaku,
            'deskripsi' => $this->deskripsi($data['judul'], $unit->nama),
            ...$berkas,
            'extracted_text' => $teksEkstraksi,
            'extraction_status' => $this->statusEkstraksi($status, $urutan, $teksEkstraksi),
            'is_shared_to_all' => $akses['is_shared_to_all'] ?? false,
            'min_tingkat_akses' => $akses['min_tingkat_akses'] ?? null,
            'edit_scope' => $urutan % 10 < 3
                ? DocumentEditScope::MatchVisibility
                : DocumentEditScope::OwnerOnly,
            'uploaded_by' => ($pengunggah ?? $this->pengunggahDari($unit))->id,
            'is_active' => true,
        ]);

        $this->terapkanStatusKadaluarsa($document);

        if (! empty($akses['units'])) {
            $document->targetUnits()->attach(
                array_fill_keys($akses['units'], ['added_by' => $document->uploaded_by]),
            );
        }

        if (! empty($akses['shares'])) {
            $document->sharedUsers()->attach(
                array_fill_keys($akses['shares'], ['granted_by' => $document->uploaded_by]),
            );
        }
    }

    // -- Penyusunan sebaran akses --------------------------------------------

    /**
     * Daftar jenis kombinasi akses sepanjang jumlah dokumen, mengikuti proporsi
     * pada `SEBARAN_AKSES`.
     *
     * @return list<string>
     */
    private function rencanaAkses(int $jumlah, int $lanjutanDari = 0): array
    {
        static $antrian = null;

        if ($antrian === null) {
            $antrian = [];
            foreach (self::SEBARAN_AKSES as $jenis => $banyak) {
                $antrian = [...$antrian, ...array_fill(0, $banyak, $jenis)];
            }
            shuffle($antrian);
        }

        return array_slice($antrian, $lanjutanDari, $jumlah);
    }

    /**
     * @return array{is_shared_to_all?: bool, min_tingkat_akses?: int, units?: list<int>, shares?: list<int>}
     */
    private function aksesDari(string $jenis): array
    {
        $unitAcak = fn (): int => (int) $this->units->random()->id;
        $orangAcak = fn (int $n): array => $this->pengguna->random($n)
            ->pluck('id')->map(intval(...))->all();

        return match ($jenis) {
            'semua' => ['is_shared_to_all' => true],
            'satu_unit' => ['units' => [$unitAcak()]],
            'unit_bertingkat' => ['units' => $this->unitBesertaTurunan(
                (int) $this->units->whereNull('parent_id')->random()->id,
            )],
            'jenjang' => ['min_tingkat_akses' => mt_rand(2, 3)],
            'orang' => ['shares' => $orangAcak(mt_rand(1, 3))],
            'dua' => ['units' => [$unitAcak()], 'min_tingkat_akses' => mt_rand(2, 3)],
            'tiga' => [
                'units' => [$unitAcak()],
                'min_tingkat_akses' => mt_rand(2, 3),
                'shares' => $orangAcak(mt_rand(1, 2)),
            ],
            // Tanpa mekanisme apa pun: hanya pengunggah, Superadmin, dan
            // jabatan tingkat 1 yang dapat melihatnya.
            'pengunggah' => [],
        };
    }

    /**
     * Unit induk beserta seluruh divisi di bawahnya.
     *
     * Cascade diselesaikan di sini, saat menyimpan — bukan dihitung ulang setiap
     * kali membaca. Dengan begitu isi `document_units` selalu mencerminkan
     * persis siapa yang berhak (`Catatan_Audit.md` isu #15).
     *
     * @return list<int>
     */
    private function unitBesertaTurunan(int $indukId): array
    {
        return [
            $indukId,
            ...$this->units->where('parent_id', $indukId)->pluck('id')->all(),
        ];
    }

    // -- Nilai atribut --------------------------------------------------------

    private function tanggalAcak(): string
    {
        // Sebaran 2024-2026, condong ke tahun terbaru supaya daftar teratas
        // berisi dokumen yang relevan.
        $tahun = [2024, 2025, 2025, 2026, 2026][mt_rand(0, 4)];

        return sprintf('%d-%02d-%02d', $tahun, mt_rand(1, 12), mt_rand(1, 28));
    }

    private function masaBerlaku(string $tanggal, int $urutan): ?string
    {
        // Delapan dokumen sengaja jatuh dalam 30 hari ke depan supaya kartu
        // "mendekati masa evaluasi" di dasbor punya isi sejak awal (FR-04).
        if ($urutan % 27 === 0 && $urutan < 220) {
            return now()->addDays(mt_rand(3, 29))->toDateString();
        }

        // Lima dokumen sudah lewat masa berlakunya tapi statusnya belum
        // berpindah — bahan demo perintah transisi otomatis (FEAT-16).
        if ($urutan % 43 === 7) {
            return now()->subDays(mt_rand(1, 20))->toDateString();
        }

        if ($urutan % 5 === 0) {
            return null;
        }

        return date('Y-m-d', strtotime($tanggal.' +'.mt_rand(180, 900).' days'));
    }

    /**
     * Perpindahan status berlaku ke kadaluarsa untuk dokumen yang masa
     * berlakunya sudah lewat lebih dari sebulan — meniru hasil kerja perintah
     * terjadwal yang sudah berjalan berkali-kali.
     */
    private function terapkanStatusKadaluarsa(Document $document): void
    {
        if ($document->masa_berlaku?->isBefore(now()->subMonth())) {
            $document->update(['status' => DocumentStatus::Kadaluarsa]);
        }
    }

    /**
     * Menyisakan contoh untuk setiap nilai status ekstraksi, supaya tiap lencana
     * punya wujud nyata segera setelah seeding — tanpa menunggu ada yang
     * mengunggah berkas rusak.
     */
    private function statusEkstraksi(ExtractionStatus $bawaan, int $urutan, ?string $teks): ExtractionStatus
    {
        if ($bawaan === ExtractionStatus::NotApplicable) {
            return $bawaan;
        }

        return match (true) {
            $urutan % 71 === 13 => ExtractionStatus::Pending,
            $urutan % 41 === 9 => ExtractionStatus::Failed,
            trim($teks ?? '') === '' => ExtractionStatus::ReviewRequired,
            default => $bawaan,
        };
    }

    private function pengunggahDari(Unit $unit): User
    {
        return $this->pengguna->firstWhere('unit_id', $unit->id)
            ?? $this->pengguna->random();
    }

    private function judulBertemplate(): string
    {
        $jenis = ['Laporan', 'Kajian', 'Rekapitulasi', 'Berita Acara', 'Usulan', 'Evaluasi', 'Pedoman'];
        $objek = [
            'Kegiatan Eksplorasi', 'Pengadaan Barang dan Jasa',
            'Pemeliharaan Fasilitas Produksi', 'Pengelolaan Aset',
            'Kepatuhan K3KS', 'Penerimaan Negara Bukan Pajak',
            'Pengembangan Sumber Daya Manusia', 'Sistem Informasi Internal',
            'Pengawasan Wilayah Kerja', 'Distribusi Hasil Produksi',
        ];
        $periode = [
            'Triwulan I', 'Triwulan II', 'Triwulan III', 'Triwulan IV',
            'Semester I', 'Semester II', 'Tahun 2024', 'Tahun 2025', 'Tahun 2026',
        ];

        return sprintf(
            '%s %s %s',
            $jenis[array_rand($jenis)],
            $objek[array_rand($objek)],
            $periode[array_rand($periode)],
        );
    }

    private function deskripsi(string $judul, string $unit): string
    {
        return "Dokumen {$judul} yang diterbitkan oleh {$unit} di lingkungan "
            .'Badan Pengelola Migas Aceh. Seluruh isi merupakan data dummy '
            .'untuk keperluan prototype.';
    }

    private function unitId(string $nama): int
    {
        return (int) $this->units->firstWhere('nama', $nama)->id;
    }

    private function penggunaByEmail(string $email): User
    {
        return $this->pengguna->firstWhere('email', $email) ?? $this->pengguna->first();
    }
}
