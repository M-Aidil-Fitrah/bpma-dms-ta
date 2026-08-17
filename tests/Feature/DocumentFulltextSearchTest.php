<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Category;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pencarian FULLTEXT atas isi dokumen (FR-34, FR-35).
 *
 * Terpisah dari `DocumentIndexTest` karena alasan teknis, bukan organisasi:
 * InnoDB (termasuk MariaDB) tidak melihat baris yang baru disisipkan dalam
 * transaksi yang SAMA yang belum di-commit lewat index FULLTEXT — walau baris
 * itu terlihat normal lewat `WHERE` biasa. `RefreshDatabase` membungkus tiap
 * tes dalam satu transaksi yang di-rollback di akhir, sehingga tes pencarian
 * di sini WAJIB memakai `DatabaseTruncation` (commit sungguhan + truncate
 * antar tes), bukan `RefreshDatabase`. Konsekuensinya lebih lambat — dipakai
 * hanya untuk tes yang benar-benar butuh membuktikan FULLTEXT bekerja.
 */
final class DocumentFulltextSearchTest extends TestCase
{
    use DatabaseTruncation;

    private User $anggota;

    private User $orangLain;

    private Unit $unit;

    private Category $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateDatabaseTables();

        Role::findOrCreate(User::ROLE_PENGGUNA, 'web');

        $jabatan = Jabatan::factory()->tingkat(4)->create();
        $this->unit = Unit::factory()->create();
        $this->kategori = Category::factory()->create();

        $this->anggota = User::factory()->create([
            'jabatan_id' => $jabatan->id,
            'unit_id' => $this->unit->id,
        ]);
        $this->anggota->assignRole(User::ROLE_PENGGUNA);

        // Pengunggah bawaan `buatDokumen()` — sengaja BUKAN `$this->anggota`.
        // Jaminan bawaan `visibleTo()` membuat pengunggah selalu melihat
        // dokumennya sendiri di luar kombinasi mekanisme akses; memakai
        // `$this->anggota` sebagai pengunggah bawaan akan membuat tes
        // penolakan akses lolos untuk alasan yang salah.
        $this->orangLain = User::factory()->create([
            'jabatan_id' => $jabatan->id,
            'unit_id' => Unit::factory()->create()->id,
        ]);
        $this->orangLain->assignRole(User::ROLE_PENGGUNA);
    }

    /**
     * `truncateDatabaseTables()` di `setUp()` membersihkan SEBELUM tes
     * milik kelas ini sendiri berjalan, tapi tidak pernah dipanggil lagi
     * setelah tes TERAKHIR di kelas ini selesai. Tanpa pembersihan di sini,
     * baris yang sempat di-commit tes FULLTEXT terakhir menjadi data awal
     * yang tidak diharapkan bagi kelas tes lain yang memakai
     * `RefreshDatabase` dan kebetulan berjalan sesudahnya — rollback
     * `RefreshDatabase` hanya membatalkan perubahan tesnya sendiri, tidak
     * pernah menyentuh baris yang sudah sungguhan ter-commit dari luar.
     */
    protected function tearDown(): void
    {
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function buatDokumen(array $atribut = []): Document
    {
        return Document::factory()->create([
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->unit->id,
            'uploaded_by' => $this->orangLain->id,
            'is_shared_to_all' => true,
            ...$atribut,
        ]);
    }

    public function test_pencarian_mencakup_judul_dan_nomor(): void
    {
        $this->buatDokumen(['judul' => 'Laporan Seismik Blok A', 'nomor' => '001/BPMA/X/I/2026']);
        $this->buatDokumen(['judul' => 'Notulen Rapat', 'nomor' => '042/BPMA/SEISMIK/I/2026']);
        $this->buatDokumen(['judul' => 'Kontrak Jasa', 'nomor' => '003/BPMA/X/I/2026']);

        $this->actingAs($this->anggota)->get('/documents?cari=seismik')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('dokumen.data', 2));
    }

    public function test_pencarian_menemukan_isi_hasil_ekstraksi(): void
    {
        // FR-34 — kata kuncinya cuma ada di dalam extracted_text (hasil
        // pdfparser/phpword), sama sekali tidak di judul, nomor, atau
        // deskripsi. Kalau ini lolos, index FULLTEXT sungguhan yang bekerja,
        // bukan cuma LIKE pada kolom lain.
        $this->buatDokumen([
            'judul' => 'Dokumen Biasa',
            'nomor' => '001/BPMA/X/I/2026',
            'deskripsi' => 'Deskripsi tidak berkaitan',
            'extracted_text' => 'Berisi laporan produksi minyak bumi cadangan blok mahakam',
        ]);
        $this->buatDokumen(['judul' => 'Dokumen Lain']);

        $this->actingAs($this->anggota)->get('/documents?cari=mahakam')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('dokumen.data', 1)
                ->where('dokumen.data.0.judul', 'Dokumen Biasa'));
    }

    public function test_pencarian_mengutamakan_judul_daripada_teks_isi_dan_tanggal(): void
    {
        // Dokumen yang lebih baru hanya cocok di isi. Tanpa ranking lapangan
        // atau ketika `urut=tanggal` bawaan dianggap urutan manual, ia akan
        // keliru mengalahkan judul yang cocok persis.
        $this->buatDokumen([
            'judul' => 'Notulen Rapat Evaluasi',
            'tanggal' => '2026-12-09',
            'extracted_text' => 'Pembahasan kajian pengawasan wilayah ada di lampiran.',
        ]);
        $this->buatDokumen([
            'judul' => 'Kajian Pengawasan Wilayah Kerja Tahun 2026',
            'tanggal' => '2026-10-25',
            'extracted_text' => 'Dokumen ringkas.',
        ]);

        $this->actingAs($this->anggota)->get('/documents?cari=kajian%20pengawasan')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('dokumen.data', 2)
                ->where('dokumen.data.0.judul', 'Kajian Pengawasan Wilayah Kerja Tahun 2026'));
    }

    public function test_pencarian_campuran_judul_dan_nomor_tidak_diperlakukan_sebagai_nomor_saja(): void
    {
        $this->buatDokumen([
            'judul' => 'Notulen Rapat Koordinasi',
            'nomor' => '002/BPMA/DPR-PPA/XII/2026',
        ]);
        $this->buatDokumen([
            'judul' => 'Notulen Rapat Lain',
            'nomor' => '003/BPMA/DPR-PPA/XII/2026',
        ]);

        $this->actingAs($this->anggota)->get('/documents?cari=notulen%20002%2FBPMA')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('dokumen.data', 1)
                ->where('dokumen.data.0.nomor', '002/BPMA/DPR-PPA/XII/2026'));
    }

    public function test_urutan_manual_tetap_mengalahkan_relevansi_pencarian(): void
    {
        $this->buatDokumen([
            'judul' => 'Notulen Rapat Evaluasi',
            'tanggal' => '2026-12-09',
            'extracted_text' => 'Pembahasan kajian pengawasan wilayah ada di lampiran.',
        ]);
        $this->buatDokumen([
            'judul' => 'Kajian Pengawasan Wilayah Kerja Tahun 2026',
            'tanggal' => '2026-10-25',
        ]);

        $this->actingAs($this->anggota)
            ->get('/documents?cari=kajian%20pengawasan&urut=tanggal&arah=desc&urut_manual=1')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('dokumen.data', 2)
                ->where('dokumen.data.0.judul', 'Notulen Rapat Evaluasi'));
    }

    public function test_hasil_pencarian_isi_hanya_mengirim_konteks_dan_cuplikan_terbatas(): void
    {
        $this->buatDokumen([
            'judul' => 'Dokumen Biasa',
            'nomor' => '001/BPMA/X/I/2026',
            'deskripsi' => 'Deskripsi tidak berkaitan',
            'extracted_text' => 'Pembahasan mahakam dimulai hari ini. Data mahakam diverifikasi kembali.',
        ]);

        $this->actingAs($this->anggota)->get('/documents?cari=mahakam')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('dokumen.data', 1)
                ->where('dokumen.data.0.kecocokan_pencarian', ['Isi dokumen'])
                ->where('dokumen.data.0.jumlah_frasa_pencarian', 2)
                ->where('dokumen.data.0.cuplikan_pencarian', 'Pembahasan mahakam dimulai hari ini. Data mahakam diverifikasi kembali.')
                ->missing('dokumen.data.0.extracted_text'));
    }

    public function test_pencarian_isi_tunduk_pada_hak_akses(): void
    {
        // FR-35 — kata kuncinya cocok persis di extracted_text, tapi
        // dokumennya tertutup bagi anggota. Pencarian isi tidak boleh jadi
        // jalan pintas yang melewati visibleTo().
        $this->buatDokumen([
            'judul' => 'Rahasia',
            'is_shared_to_all' => false,
            'extracted_text' => 'Berisi kata kunci rahasia sekali',
        ]);

        $this->actingAs($this->anggota)->get('/documents?cari=rahasia')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('dokumen.data', 0));
    }

    public function test_penyaring_tidak_pernah_membocorkan_dokumen_di_luar_hak(): void
    {
        // Sama seperti di atas, tapi lewat judul, bukan extracted_text.
        $this->buatDokumen(['judul' => 'Rahasia Seismik', 'is_shared_to_all' => false]);

        $this->actingAs($this->anggota)->get('/documents?cari=Rahasia')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('dokumen.data', 0));
    }

    public function test_kata_di_bawah_tiga_huruf_tidak_ditemukan_lewat_isi_tapi_tetap_lewat_judul(): void
    {
        // InnoDB (innodb_ft_min_token_size bawaan 3) tidak mengindeks kata
        // di bawah 3 huruf sama sekali — mencari "K3" di dalam isi dokumen
        // tidak akan pernah menemukan apa pun, berapa pun kata itu diulang.
        // Jaring pengaman LIKE dibatasi ke judul dan nomor.
        $this->buatDokumen([
            'judul' => 'Dokumen Biasa',
            'extracted_text' => 'Prosedur K3 wajib dipatuhi seluruh pekerja lapangan',
        ]);
        $this->buatDokumen(['judul' => 'Sertifikat K3 Lapangan']);

        $this->actingAs($this->anggota)->get('/documents?cari=K3')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('dokumen.data', 1)
                ->where('dokumen.data.0.judul', 'Sertifikat K3 Lapangan'));
    }

    public function test_beberapa_penyaring_dapat_digabung(): void
    {
        $this->buatDokumen(['judul' => 'Laporan Alpha', 'status' => DocumentStatus::Berlaku]);
        $this->buatDokumen(['judul' => 'Laporan Beta', 'status' => DocumentStatus::Kadaluarsa]);
        $this->buatDokumen(['judul' => 'Notulen Alpha', 'status' => DocumentStatus::Berlaku]);

        $this->actingAs($this->anggota)
            ->get('/documents?cari=Laporan&status=berlaku')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('dokumen.data', 1)
                ->where('dokumen.data.0.judul', 'Laporan Alpha'));
    }
}
