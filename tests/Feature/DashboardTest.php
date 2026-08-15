<?php

declare(strict_types=1);

namespace Tests\Feature;

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
 * Dasbor menampilkan ringkasan yang tunduk hak akses (FR-01 s.d. FR-05).
 */
final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $anggota;

    private User $orangLain;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(User::ROLE_PENGGUNA, 'web');

        $jabatan = Jabatan::factory()->tingkat(4)->create();
        $this->unit = Unit::factory()->create();

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
            'category_id' => Category::factory(),
            'origin_unit_id' => $this->unit->id,
            'uploaded_by' => $this->orangLain->id,
            ...$atribut,
        ]);
    }

    public function test_tamu_tidak_dapat_membuka_dasbor(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_angka_dasbor_hanya_menghitung_dokumen_yang_berhak_dilihat(): void
    {
        $this->buatDokumen(['is_shared_to_all' => true]);
        $this->buatDokumen(['is_shared_to_all' => true]);
        // Tertutup bagi anggota — tidak boleh ikut terhitung.
        $this->buatDokumen();
        $this->buatDokumen();

        $this->actingAs($this->anggota)
            ->get('/dashboard')
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Dashboard')
                    ->where('data.total', 2),
            );
    }

    public function test_dua_akun_melihat_angka_yang_berbeda(): void
    {
        $this->buatDokumen(['is_shared_to_all' => true]);
        $this->buatDokumen(); // hanya terlihat pengunggahnya

        $this->actingAs($this->anggota)->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $p) => $p->where('data.total', 1));

        $this->actingAs($this->orangLain)->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $p) => $p->where('data.total', 2));
    }

    public function test_dokumen_nonaktif_tidak_ikut_dihitung(): void
    {
        $this->buatDokumen(['is_shared_to_all' => true]);
        $this->buatDokumen(['is_shared_to_all' => true, 'is_active' => false]);

        $this->actingAs($this->anggota)->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $p) => $p->where('data.total', 1));
    }

    public function test_rentang_masa_evaluasi_dapat_dipilih_pengguna(): void
    {
        $this->buatDokumen([
            'is_shared_to_all' => true,
            'masa_berlaku' => now()->addDays(5)->toDateString(),
        ]);
        $this->buatDokumen([
            'is_shared_to_all' => true,
            'masa_berlaku' => now()->addDays(60)->toDateString(),
        ]);

        $this->actingAs($this->anggota)->get('/dashboard?rentang=7')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('data.rentang_evaluasi', 7)
                ->where('data.jumlah_mendekati_evaluasi', 1));

        $this->actingAs($this->anggota)->get('/dashboard?rentang=90')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('data.rentang_evaluasi', 90)
                ->where('data.jumlah_mendekati_evaluasi', 2));
    }

    public function test_rentang_di_luar_pilihan_yang_tersedia_diabaikan(): void
    {
        // Query string yang disunting sembarangan tidak boleh menghasilkan
        // rentang yang tidak masuk akal.
        $this->actingAs($this->anggota)->get('/dashboard?rentang=99999')
            ->assertInertia(fn (AssertableInertia $p) => $p->where('data.rentang_evaluasi', 30));

        $this->actingAs($this->anggota)->get('/dashboard?rentang=abc')
            ->assertInertia(fn (AssertableInertia $p) => $p->where('data.rentang_evaluasi', 30));
    }

    public function test_ringkasan_kategori_dihitung_per_kategori(): void
    {
        $satu = Category::factory()->create(['nama' => 'Kategori Satu']);
        $dua = Category::factory()->create(['nama' => 'Kategori Dua']);

        $this->buatDokumen(['is_shared_to_all' => true, 'category_id' => $satu->id]);
        $this->buatDokumen(['is_shared_to_all' => true, 'category_id' => $satu->id]);
        $this->buatDokumen(['is_shared_to_all' => true, 'category_id' => $dua->id]);

        $this->actingAs($this->anggota)->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('data.per_kategori', 2)
                ->where('data.per_kategori.0.nama', 'Kategori Dua')
                ->where('data.per_kategori.0.jumlah', 1)
                ->where('data.per_kategori.1.nama', 'Kategori Satu')
                ->where('data.per_kategori.1.jumlah', 2));
    }

    /**
     * Membuat sejumlah dokumen yang terlihat oleh anggota.
     */
    private function isiDokumen(int $jumlah): void
    {
        Document::factory()->count($jumlah)->create([
            'category_id' => Category::factory(),
            'origin_unit_id' => $this->unit->id,
            'uploaded_by' => $this->orangLain->id,
            'is_shared_to_all' => true,
            'masa_berlaku' => now()->addDays(10)->toDateString(),
        ]);
    }

    private function hitungQueryDasbor(): int
    {
        $jumlah = 0;
        DB::listen(function () use (&$jumlah): void {
            $jumlah++;
        });

        $this->get('/dashboard')->assertOk();

        DB::flushQueryLog();

        return $jumlah;
    }

    public function test_jumlah_query_dasbor_tidak_bertambah_seiring_data(): void
    {
        /*
         * Ini pemeriksaan yang sebenarnya penting.
         *
         * Angka mutlaknya boleh diperdebatkan — dasbor merender lima kumpulan
         * data sekaligus, jadi wajar lebih mahal daripada halaman berdaftar
         * tunggal. Yang tidak boleh adalah jumlahnya IKUT BERTAMBAH seiring
         * banyaknya dokumen: itu tanda ada relasi yang diambil per baris, dan
         * biayanya meledak diam-diam saat data sungguhan masuk.
         */
        $this->actingAs($this->anggota);
        $this->isiDokumen(5);

        // Permintaan pemanasan. Permintaan pertama dalam satu tes ikut memuat
        // pengguna, jabatan, dan daftar role — yang setelahnya tersimpan di
        // cache. Tanpa pemanasan, perbandingannya mengukur pemanasan itu, bukan
        // pertumbuhan akibat data.
        $this->hitungQueryDasbor();

        $sedikit = $this->hitungQueryDasbor();

        $this->isiDokumen(45);
        $banyak = $this->hitungQueryDasbor();

        $this->assertSame(
            $sedikit,
            $banyak,
            "Query bertambah dari {$sedikit} menjadi {$banyak} saat data bertambah — "
            .'tanda ada relasi yang diambil per baris.',
        );
    }

    public function test_dasbor_tetap_dalam_anggaran_query_yang_wajar(): void
    {
        // Ambang ini menahan pertumbuhan diam-diam. Dasbor sekarang memakai 13
        // query: 3 untuk autentikasi, 1 rekap statistik gabungan, 1 komposisi
        // kategori, dan 8 untuk dua daftar ringkas beserta relasinya.
        $this->actingAs($this->anggota);
        $this->isiDokumen(20);

        $jumlah = $this->hitungQueryDasbor();

        $this->assertLessThanOrEqual(
            14,
            $jumlah,
            "Dasbor menembakkan {$jumlah} query. Periksa eager loading yang terlewat.",
        );
    }

    public function test_extracted_text_tidak_ikut_terambil_di_ringkasan(): void
    {
        $this->buatDokumen([
            'is_shared_to_all' => true,
            'extracted_text' => str_repeat('isi dokumen yang sangat panjang ', 500),
        ]);

        $this->actingAs($this->anggota);

        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = $query->sql;
        });

        $this->get('/dashboard')->assertOk();

        foreach ($sql as $statement) {
            $this->assertStringNotContainsString(
                'extracted_text',
                $statement,
                'Kolom longText tidak boleh ikut terambil pada ringkasan dasbor.',
            );
        }
    }
}
