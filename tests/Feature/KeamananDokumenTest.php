<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use App\Support\PenyajianBerkas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Keamanan penyajian berkas dan masa berlaku sesi (FR-09, FR-09b, FR-27).
 *
 * Dua titik terlemah aplikasi ini bertemu di sini: berkas yang isinya dikuasai
 * pengguna, dan sesi yang menentukan siapa boleh membacanya.
 */
final class KeamananDokumenTest extends TestCase
{
    use RefreshDatabase;

    private User $pengunggah;

    private Unit $unit;

    private Category $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(User::ROLE_PENGGUNA, 'web');

        $jabatan = Jabatan::factory()->tingkat(4)->create();
        $this->unit = Unit::factory()->tingkatAtas()->create();
        $this->kategori = Category::factory()->create();

        $this->pengunggah = User::factory()->create([
            'jabatan_id' => $jabatan->id,
            'unit_id' => $this->unit->id,
        ]);
        $this->pengunggah->assignRole(User::ROLE_PENGGUNA);

        // Lihat komentar yang sama di DocumentUploadTest::setUp() — berkas
        // di tes ini juga bukan PDF/DOCX sungguhan.
        Queue::fake();
    }

    private function unggah(string $nama, string $isi = 'isi berkas'): Document
    {
        $this->actingAs($this->pengunggah)->post('/documents', [
            'nomor' => '001/UJI/VIII/2026',
            'judul' => 'Berkas Uji',
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->unit->id,
            'tanggal' => '2026-08-15',
            'edit_scope' => 'owner_only',
            'is_shared_to_all' => true,
            'file' => UploadedFile::fake()->createWithContent($nama, $isi),
        ]);

        $dokumen = Document::firstWhere('judul', 'Berkas Uji');
        $this->assertNotNull($dokumen, "Berkas {$nama} tidak tersimpan.");

        return $dokumen;
    }

    // -- Berkas yang dapat memuat skrip ---------------------------------------

    /**
     * @return list<array{string, string}>
     */
    public static function berkasBerskrip(): array
    {
        return [
            'html' => ['jahat.html', '<script>alert(document.cookie)</script>'],
            'htm' => ['jahat.htm', '<script>alert(1)</script>'],
            'svg' => ['jahat.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'xhtml' => ['jahat.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></html>'],
        ];
    }

    #[DataProvider('berkasBerskrip')]
    public function test_berkas_berpotensi_skrip_tidak_pernah_disajikan_inline(
        string $nama,
        string $isi,
    ): void {
        // Inti temuannya: berkas ini boleh diunggah dan boleh diunduh, tapi
        // TIDAK boleh dijalankan peramban pada asal aplikasi. Kalau tampil
        // inline, siapa pun yang boleh mengunggah dapat menjalankan skrip di
        // dalam sesi orang lain dan mengambil dokumen yang dapat orang itu
        // akses.
        $dokumen = $this->unggah($nama, $isi);

        $respons = $this->actingAs($this->pengunggah)
            ->get("/documents/{$dokumen->id}/preview")
            ->assertOk();

        $this->assertStringStartsWith(
            'attachment',
            (string) $respons->headers->get('Content-Disposition'),
            "Berkas {$nama} disajikan inline — skrip di dalamnya akan berjalan.",
        );

        $this->assertStringNotContainsString(
            'text/html',
            (string) $respons->headers->get('Content-Type'),
        );
    }

    #[DataProvider('berkasBerskrip')]
    public function test_unduhan_berkas_berskrip_juga_dilindungi(
        string $nama,
        string $isi,
    ): void {
        $dokumen = $this->unggah($nama, $isi);

        $respons = $this->actingAs($this->pengunggah)
            ->get("/documents/{$dokumen->id}/file")
            ->assertOk();

        $this->assertSame('nosniff', $respons->headers->get('X-Content-Type-Options'));
    }

    public function test_header_keamanan_menyertai_setiap_penyajian_berkas(): void
    {
        $dokumen = $this->unggah('laporan.pdf', '%PDF-1.4 palsu');

        foreach (['preview', 'file'] as $jalur) {
            $respons = $this->actingAs($this->pengunggah)
                ->get("/documents/{$dokumen->id}/{$jalur}")
                ->assertOk();

            $this->assertSame(
                'nosniff',
                $respons->headers->get('X-Content-Type-Options'),
                "Rute {$jalur} membiarkan peramban menebak tipe berkas.",
            );
            $this->assertStringContainsString(
                'sandbox',
                (string) $respons->headers->get('Content-Security-Policy'),
                "Rute {$jalur} tanpa sandbox.",
            );
        }
    }

    public function test_pdf_dan_gambar_tetap_dapat_dipratinjau(): void
    {
        // Pengetatan tidak boleh sampai mematikan gunanya pratinjau.
        foreach (['laporan.pdf', 'foto.jpg', 'gambar.png'] as $nama) {
            // Dokumen baru menunjuk dirinya sendiri sebagai akar versi. Putus
            // referensi itu sebelum force delete agar MySQL tidak menolak
            // pembersihan fixture pada iterasi berikutnya.
            Document::query()->update(['version_root_id' => null]);
            Document::query()->forceDelete();

            $dokumen = $this->unggah($nama);

            $respons = $this->actingAs($this->pengunggah)
                ->get("/documents/{$dokumen->id}/preview")
                ->assertOk();

            $this->assertStringStartsWith(
                'inline',
                (string) $respons->headers->get('Content-Disposition'),
                "{$nama} semestinya masih dapat dipratinjau.",
            );
        }
    }

    public function test_daftar_boleh_inline_tidak_memuat_tipe_yang_dapat_menjalankan_skrip(): void
    {
        // Penjaga terhadap penambahan yang ceroboh di kemudian hari.
        foreach (PenyajianBerkas::AMAN_INLINE as $mime) {
            $this->assertNotContains($mime, [
                'text/html',
                'application/xhtml+xml',
                'image/svg+xml',
                'application/xml',
                'text/xml',
            ], "Tipe {$mime} dapat menjalankan skrip dan tidak boleh inline.");
        }
    }

    // -- Otorisasi berkas -----------------------------------------------------

    public function test_pengguna_tanpa_hak_tidak_dapat_mengunduh_maupun_mempratinjau(): void
    {
        $this->actingAs($this->pengunggah)->post('/documents', [
            'nomor' => '002/UJI/VIII/2026',
            'judul' => 'Berkas Rahasia',
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->unit->id,
            'tanggal' => '2026-08-15',
            'edit_scope' => 'owner_only',
            'is_shared_to_all' => false,
            'shared_user_ids' => [],
            'unit_ids' => [$this->unit->id],
        ]);

        $dokumen = Document::firstWhere('judul', 'Berkas Rahasia');
        $this->assertNull($dokumen, 'Unggahan tanpa berkas semestinya ditolak.');

        // Dokumen yang hanya dibagikan ke satu unit tertentu.
        $dokumen = $this->unggah('rahasia.pdf');
        $dokumen->update(['is_shared_to_all' => false]);

        $orangLain = User::factory()->create([
            'jabatan_id' => $this->pengunggah->jabatan_id,
            'unit_id' => Unit::factory()->tingkatAtas()->create()->id,
        ]);
        $orangLain->assignRole(User::ROLE_PENGGUNA);

        $this->actingAs($orangLain)
            ->get("/documents/{$dokumen->id}/file")
            ->assertForbidden();

        $this->actingAs($orangLain)
            ->get("/documents/{$dokumen->id}/preview")
            ->assertForbidden();
    }

    public function test_tamu_tidak_dapat_menyentuh_berkas(): void
    {
        $dokumen = $this->unggah('publik.pdf');

        $this->post('/logout');

        $this->get("/documents/{$dokumen->id}/file")->assertRedirect(route('login'));
        $this->get("/documents/{$dokumen->id}/preview")->assertRedirect(route('login'));
    }

    // -- Sesi akun yang dinonaktifkan -----------------------------------------

    public function test_akun_yang_dinonaktifkan_langsung_kehilangan_sesinya(): void
    {
        // Menonaktifkan akun adalah cara memberhentikan akses seseorang. Kalau
        // sesi yang sedang berjalan dibiarkan hidup, penonaktifannya baru
        // berlaku setelah sesi itu kedaluwarsa sendiri — bisa berbulan-bulan
        // bila "ingat saya" aktif.
        $this->actingAs($this->pengunggah)->get('/dashboard')->assertOk();

        $this->pengunggah->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_pengguna_diberi_tahu_alasan_ia_dikeluarkan(): void
    {
        // Dialihkan ke halaman masuk tanpa penjelasan membuat orang mengira
        // sistemnya rusak, lalu mencoba berulang kali dengan sandi yang benar.
        $this->actingAs($this->pengunggah)->get('/dashboard')->assertOk();
        $this->pengunggah->update(['is_active' => false]);

        // Pengalihan diikuti pada permintaan yang sama. Permintaan berikutnya
        // sudah datang sebagai tamu, dan flash-nya memang sudah habis terpakai —
        // memeriksanya di sana akan menguji hal yang keliru.
        //
        // `status` adalah kunci yang benar-benar dirender halaman masuk; pesan
        // yang tersimpan di kunci lain sama saja dengan tidak ada.
        $this->followingRedirects()
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(
                fn ($halaman) => $halaman
                    ->component('Auth/Login')
                    ->where('status', 'Akun Anda telah dinonaktifkan. Hubungi administrator sistem.'),
            );
    }

    public function test_akun_dinonaktifkan_tidak_dapat_mengunduh_berkas_lagi(): void
    {
        $dokumen = $this->unggah('laporan.pdf');

        $this->actingAs($this->pengunggah)
            ->get("/documents/{$dokumen->id}/file")
            ->assertOk();

        $this->pengunggah->update(['is_active' => false]);

        $this->get("/documents/{$dokumen->id}/file")->assertRedirect(route('login'));
    }

    public function test_akun_aktif_tidak_terganggu_pemeriksaan_ini(): void
    {
        $this->actingAs($this->pengunggah)->get('/dashboard')->assertOk();
        $this->actingAs($this->pengunggah)->get('/documents')->assertOk();
        $this->assertAuthenticated();
    }
}
