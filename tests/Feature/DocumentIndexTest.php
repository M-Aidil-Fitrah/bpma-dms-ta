<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use App\Models\Category;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Daftar dokumen: penyaringan, pengurutan, dan pagination (FR-16 s.d. FR-22).
 */
final class DocumentIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $anggota;

    private User $orangLain;

    private Unit $unit;

    private Category $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(User::ROLE_PENGGUNA, 'web');

        $jabatan = Jabatan::factory()->tingkat(4)->create();
        $this->unit = Unit::factory()->create(['nama' => 'Divisi Uji']);
        $this->kategori = Category::factory()->create(['nama' => 'Kategori Uji']);

        $this->anggota = User::factory()->create([
            'jabatan_id' => $jabatan->id,
            'unit_id' => $this->unit->id,
        ]);
        $this->anggota->assignRole(User::ROLE_PENGGUNA);

        $this->orangLain = User::factory()->create([
            'jabatan_id' => $jabatan->id,
            'unit_id' => Unit::factory()->create()->id,
        ]);
        $this->orangLain->assignRole(User::ROLE_PENGGUNA);
    }

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

    public function test_tamu_tidak_dapat_membuka_daftar(): void
    {
        $this->get('/documents')->assertRedirect(route('login'));
    }

    public function test_hanya_dokumen_yang_berhak_dilihat_yang_tampil(): void
    {
        $this->buatDokumen(['judul' => 'Terbuka']);
        $this->buatDokumen(['judul' => 'Tertutup', 'is_shared_to_all' => false]);

        $this->actingAs($this->anggota)->get('/documents')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Documents/Index')
                ->has('dokumen.data', 1)
                ->where('dokumen.data.0.judul', 'Terbuka'));
    }

    public function test_daftar_hanya_menandai_berkas_yang_bisa_dipratinjau_di_tab_baru(): void
    {
        $this->buatDokumen([
            'file_mime_type' => 'application/zip',
            'file_name_original' => 'arsip.zip',
            'tanggal' => '2026-01-01',
        ]);
        $this->buatDokumen([
            'file_mime_type' => 'application/pdf',
            'file_name_original' => 'laporan.pdf',
            'tanggal' => '2026-02-01',
        ]);
        $this->buatDokumen([
            'file_mime_type' => 'video/mp4',
            'file_name_original' => 'rekaman.mp4',
            'tanggal' => '2026-03-01',
        ]);

        $this->actingAs($this->anggota)->get('/documents')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('dokumen.data', 3)
                ->where('dokumen.data.0.bisa_pratinjau_di_tab_baru', true)
                ->where('dokumen.data.1.bisa_pratinjau_di_tab_baru', true)
                ->where('dokumen.data.2.bisa_pratinjau_di_tab_baru', false));
    }

    public function test_dokumen_nonaktif_tidak_tampil(): void
    {
        $this->buatDokumen(['judul' => 'Aktif']);
        $this->buatDokumen(['judul' => 'Nonaktif', 'is_active' => false]);

        $this->actingAs($this->anggota)->get('/documents')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('dokumen.data', 1));
    }

    // -- Pagination -----------------------------------------------------------

    public function test_daftar_dipecah_menjadi_halaman(): void
    {
        Document::factory()->count(25)->create([
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->unit->id,
            'uploaded_by' => $this->orangLain->id,
            'is_shared_to_all' => true,
        ]);

        $perHalaman = config('dms.dokumen.per_halaman');

        $this->actingAs($this->anggota)->get('/documents')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('dokumen.data', $perHalaman)
                ->where('dokumen.total', 25)
                ->where('dokumen.current_page', 1)
                ->where('dokumen.last_page', 2));
    }

    public function test_halaman_kedua_menampilkan_sisa_dokumen(): void
    {
        Document::factory()->count(25)->create([
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->unit->id,
            'uploaded_by' => $this->orangLain->id,
            'is_shared_to_all' => true,
        ]);

        $this->actingAs($this->anggota)->get('/documents?page=2')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('dokumen.data', 5)
                ->where('dokumen.current_page', 2));
    }

    public function test_penyaring_ikut_terbawa_saat_berpindah_halaman(): void
    {
        Document::factory()->count(25)->create([
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->unit->id,
            'uploaded_by' => $this->orangLain->id,
            'is_shared_to_all' => true,
            'status' => DocumentStatus::Berlaku,
        ]);

        // Tanpa `withQueryString()`, berpindah halaman diam-diam mengosongkan
        // penyaring yang baru saja dipasang pengguna.
        $this->actingAs($this->anggota)->get('/documents?status=berlaku&page=2')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('filter.status', 'berlaku')
                ->where('dokumen.current_page', 2));
    }

    public function test_tidak_ada_dokumen_yang_muncul_dua_kali_antar_halaman(): void
    {
        // Seluruh dokumen bertanggal sama. Tanpa pengurutan kedua yang tetap,
        // basis data bebas menyusun ulang baris bertanggal kembar di antara dua
        // permintaan — dokumen yang sama bisa muncul di dua halaman sekaligus,
        // sementara yang lain tidak muncul sama sekali.
        Document::factory()->count(30)->create([
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->unit->id,
            'uploaded_by' => $this->orangLain->id,
            'is_shared_to_all' => true,
            'tanggal' => '2026-01-01',
        ]);

        $this->actingAs($this->anggota);

        $idHalaman1 = $this->ambilId('/documents');
        $idHalaman2 = $this->ambilId('/documents?page=2');

        $this->assertCount(30, array_unique([...$idHalaman1, ...$idHalaman2]));
    }

    /**
     * @return list<int>
     */
    private function ambilId(string $url): array
    {
        $props = $this->get($url)->viewData('page')['props'];

        return array_column($props['dokumen']['data'], 'id');
    }

    // -- Penyaring ------------------------------------------------------------

    // Pencarian kata kunci (`cari`) diuji di `DocumentFulltextSearchTest`,
    // bukan di sini — FULLTEXT MariaDB tidak melihat baris yang baru dibuat
    // dalam transaksi yang sama yang belum di-commit, dan `RefreshDatabase`
    // di berkas ini membungkus tiap tes dalam transaksi yang di-rollback.

    public function test_penyaring_kategori_unit_dan_status_bekerja(): void
    {
        $kategoriLain = Category::factory()->create();
        $unitLain = Unit::factory()->create();

        $this->buatDokumen(['status' => DocumentStatus::Berlaku]);
        $this->buatDokumen(['category_id' => $kategoriLain->id]);
        $this->buatDokumen(['origin_unit_id' => $unitLain->id]);
        $this->buatDokumen(['status' => DocumentStatus::Kadaluarsa]);

        $this->actingAs($this->anggota);

        $this->get("/documents?kategori={$this->kategori->id}")
            ->assertInertia(fn (AssertableInertia $p) => $p->has('dokumen.data', 3));

        $this->get("/documents?unit={$unitLain->id}")
            ->assertInertia(fn (AssertableInertia $p) => $p->has('dokumen.data', 1));

        $this->get('/documents?status=kadaluarsa')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('dokumen.data', 1));
    }

    public function test_penyaring_rentang_tanggal_bekerja(): void
    {
        $this->buatDokumen(['tanggal' => '2025-06-15']);
        $this->buatDokumen(['tanggal' => '2026-03-10']);
        $this->buatDokumen(['tanggal' => '2026-11-20']);

        $this->actingAs($this->anggota)
            ->get('/documents?dari=2026-01-01&sampai=2026-06-30')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('dokumen.data', 1));
    }

    public function test_penyaring_pengunggah_tipe_dan_status_ekstraksi_bekerja_bersamaan(): void
    {
        $pengunggahLain = User::factory()->create([
            'jabatan_id' => $this->anggota->jabatan_id,
            'unit_id' => $this->unit->id,
        ]);
        $pengunggahLain->assignRole(User::ROLE_PENGGUNA);

        $cocok = $this->buatDokumen([
            'judul' => 'Gambar Perlu Tinjau',
            'uploaded_by' => $pengunggahLain->id,
            'file_mime_type' => 'image/jpeg',
            'extraction_status' => ExtractionStatus::ReviewRequired,
        ]);
        $this->buatDokumen([
            'judul' => 'PDF Pengunggah Sama',
            'uploaded_by' => $pengunggahLain->id,
            'file_mime_type' => 'application/pdf',
            'extraction_status' => ExtractionStatus::Completed,
        ]);
        $this->buatDokumen([
            'judul' => 'Gambar Pengunggah Lain',
            'file_mime_type' => 'image/jpeg',
            'extraction_status' => ExtractionStatus::ReviewRequired,
        ]);

        $this->actingAs($this->anggota)
            ->get("/documents?pengunggah={$pengunggahLain->id}&tipe=gambar&status_ekstraksi=review_required")
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('dokumen.data', 1)
                ->where('dokumen.data.0.id', $cocok->id)
                ->where('filter.pengunggah', $pengunggahLain->id)
                ->where('filter.tipe', 'gambar')
                ->where('filter.status_ekstraksi', 'review_required'));
    }

    public function test_tanggal_akhir_tidak_boleh_mendahului_tanggal_awal(): void
    {
        $this->actingAs($this->anggota)
            ->get('/documents?dari=2026-06-01&sampai=2026-01-01')
            ->assertSessionHasErrors('sampai');
    }

    // -- Pengurutan -----------------------------------------------------------

    public function test_pengurutan_mengikuti_kolom_yang_diminta(): void
    {
        $this->buatDokumen(['judul' => 'Beta']);
        $this->buatDokumen(['judul' => 'Alpha']);

        $this->actingAs($this->anggota)->get('/documents?urut=judul&arah=asc')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('dokumen.data.0.judul', 'Alpha'));
    }

    public function test_kolom_pengurutan_di_luar_daftar_ditolak(): void
    {
        // Nama kolom tidak pernah di-escape Eloquent seperti halnya nilai,
        // sehingga meneruskannya mentah-mentah dari query string membuka jalan
        // injeksi lewat nama kolom.
        $this->actingAs($this->anggota)
            ->get('/documents?urut=extracted_text')
            ->assertSessionHasErrors('urut');
    }

    // -- Performa -------------------------------------------------------------

    public function test_extracted_text_tidak_pernah_ikut_terambil(): void
    {
        $this->buatDokumen([
            'extracted_text' => str_repeat('isi dokumen yang sangat panjang ', 500),
        ]);

        $this->actingAs($this->anggota);

        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = $query->sql;
        });

        $this->get('/documents')->assertOk();

        foreach ($sql as $statement) {
            $this->assertStringNotContainsString('extracted_text', $statement);
        }
    }

    public function test_pencarian_memakai_index_fulltext(): void
    {
        // Tanpa bukti EXPLAIN ini, MATCH...AGAINST bisa saja diam-diam jatuh
        // ke pemindaian tabel penuh — hasilnya tetap benar tapi lambat begitu
        // datanya besar, dan tidak ada tes lain yang menangkap regresi itu.
        $this->buatDokumen(['extracted_text' => 'kandungan minyak bumi cadangan']);

        $this->actingAs($this->anggota);

        $sql = null;
        $bindings = null;
        DB::listen(function ($query) use (&$sql, &$bindings): void {
            if (str_contains($query->sql, 'match')) {
                $sql = $query->sql;
                $bindings = $query->bindings;
            }
        });

        $this->get('/documents?cari=minyak')->assertOk();

        $this->assertNotNull($sql, 'Query dengan MATCH...AGAINST tidak ditemukan.');

        $baris = DB::select('EXPLAIN '.$sql, $bindings);
        $tipeAkses = collect($baris)->pluck('type')->implode(',');

        $this->assertStringContainsString(
            'fulltext',
            $tipeAkses,
            "EXPLAIN tidak menunjukkan akses fulltext (type: {$tipeAkses}).",
        );
    }

    public function test_jumlah_query_tidak_bertambah_seiring_baris(): void
    {
        $this->actingAs($this->anggota);

        $hitung = function (): int {
            $jumlah = 0;
            DB::listen(function () use (&$jumlah): void {
                $jumlah++;
            });
            $this->get('/documents')->assertOk();

            return $jumlah;
        };

        Document::factory()->count(3)->create([
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->unit->id,
            'uploaded_by' => $this->orangLain->id,
            'is_shared_to_all' => true,
        ]);
        $hitung(); // pemanasan autentikasi dan cache role
        $sedikit = $hitung();

        Document::factory()->count(17)->create([
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->unit->id,
            'uploaded_by' => $this->orangLain->id,
            'is_shared_to_all' => true,
        ]);
        $banyak = $hitung();

        $this->assertSame(
            $sedikit,
            $banyak,
            "Query bertambah dari {$sedikit} menjadi {$banyak} — tanda relasi diambil per baris.",
        );
    }
}
