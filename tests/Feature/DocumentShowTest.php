<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentEditScope;
use App\Models\Category;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Halaman detail, unduhan, dan pratinjau berkas (FR-07, FR-09, FR-09b).
 *
 * Yang paling penting di sini bukan tampilannya, melainkan bahwa ketiga rute
 * tunduk pada Policy yang sama. Rute berkas yang lebih longgar daripada halaman
 * detailnya akan menjadi pintu belakang: seluruh sistem mekanisme akses dapat
 * dilewati hanya dengan menebak alamat berkasnya.
 */
final class DocumentShowTest extends TestCase
{
    use RefreshDatabase;

    private User $berhak;

    private User $tidakBerhak;

    private User $pengunggah;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(User::ROLE_PENGGUNA, 'web');

        $jabatan = Jabatan::factory()->tingkat(4)->create();

        foreach (['berhak', 'tidakBerhak', 'pengunggah'] as $peran) {
            $user = User::factory()->create([
                'jabatan_id' => $jabatan->id,
                'unit_id' => Unit::factory()->create()->id,
            ]);
            $user->assignRole(User::ROLE_PENGGUNA);
            $this->{$peran} = $user;
        }
    }

    private function buatDokumen(array $atribut = []): Document
    {
        $document = Document::factory()->create([
            'category_id' => Category::factory(),
            'origin_unit_id' => Unit::factory()->create()->id,
            'uploaded_by' => $this->pengunggah->id,
            'is_shared_to_all' => true,
            ...$atribut,
        ]);

        // Berkas sungguhan disiapkan di penyimpanan tiruan supaya rute unduhan
        // dan pratinjau benar-benar mengalirkan sesuatu, bukan langsung 404.
        Storage::disk('local')->put($document->file_path, 'isi berkas uji');

        return $document;
    }

    // -- Halaman detail -------------------------------------------------------

    public function test_tamu_dialihkan_ke_halaman_masuk(): void
    {
        $document = $this->buatDokumen();

        $this->get("/documents/{$document->id}")->assertRedirect(route('login'));
    }

    public function test_pengguna_berhak_dapat_membuka_detail(): void
    {
        $document = $this->buatDokumen(['judul' => 'Dokumen Terbuka']);

        $this->actingAs($this->berhak)
            ->get("/documents/{$document->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Documents/Show')
                ->where('dokumen.judul', 'Dokumen Terbuka')
                ->where('dokumen.id', $document->id));
    }

    public function test_dokumen_di_luar_hak_menghasilkan_403(): void
    {
        // Kriteria Penerimaan #4 — akses langsung ke ID harus ditolak.
        $document = $this->buatDokumen(['is_shared_to_all' => false]);

        $this->actingAs($this->tidakBerhak)
            ->get("/documents/{$document->id}")
            ->assertForbidden();
    }

    public function test_isi_teks_ikut_dimuat_di_halaman_detail(): void
    {
        // Kebalikan dari aturan halaman daftar: di sini `extracted_text` justru
        // wajib ada, karena panel teks pratinjau dibangun darinya.
        $document = $this->buatDokumen(['extracted_text' => 'isi dokumen yang terbaca']);

        $this->actingAs($this->berhak)
            ->get("/documents/{$document->id}")
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('dokumen.isi_teks', 'isi dokumen yang terbaca'));
    }

    public function test_wewenang_mengubah_mengikuti_edit_scope(): void
    {
        $document = $this->buatDokumen(['edit_scope' => DocumentEditScope::OwnerOnly]);

        $this->actingAs($this->pengunggah)
            ->get("/documents/{$document->id}")
            ->assertInertia(fn (AssertableInertia $p) => $p->where('dokumen.boleh_ubah', true));

        $this->actingAs($this->berhak)
            ->get("/documents/{$document->id}")
            ->assertInertia(fn (AssertableInertia $p) => $p->where('dokumen.boleh_ubah', false));
    }

    public function test_seluruh_mekanisme_akses_dikirim_ke_antarmuka(): void
    {
        $unit = Unit::factory()->create();
        $document = $this->buatDokumen([
            'is_shared_to_all' => false,
            'min_tingkat_akses' => 2,
        ]);
        $document->targetUnits()->attach($unit->id);
        $document->sharedUsers()->attach($this->berhak->id);

        $this->actingAs($this->berhak)
            ->get("/documents/{$document->id}")
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('dokumen.dibagikan_ke_semua', false)
                ->where('dokumen.min_tingkat_akses', 2)
                ->has('dokumen.unit_tujuan', 1)
                ->has('dokumen.orang_tertentu', 1));
    }

    public function test_halaman_detail_tidak_boros_query(): void
    {
        $document = $this->buatDokumen();
        $document->targetUnits()->attach(Unit::factory()->create()->id);
        $document->sharedUsers()->attach($this->berhak->id);

        $this->actingAs($this->berhak);
        $this->get("/documents/{$document->id}"); // pemanasan autentikasi

        $jumlah = 0;
        DB::listen(function () use (&$jumlah): void {
            $jumlah++;
        });

        $this->get("/documents/{$document->id}")->assertOk();

        $this->assertLessThanOrEqual(
            12,
            $jumlah,
            "Halaman detail menembakkan {$jumlah} query. Periksa relasi yang belum dimuat.",
        );
    }

    // -- Unduhan --------------------------------------------------------------

    public function test_unduhan_memakai_content_disposition_attachment(): void
    {
        $document = $this->buatDokumen(['file_name_original' => 'laporan.pdf']);

        $this->actingAs($this->berhak)
            ->get("/documents/{$document->id}/file")
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=laporan.pdf');
    }

    public function test_unduhan_ditolak_bagi_yang_tidak_berhak(): void
    {
        $document = $this->buatDokumen(['is_shared_to_all' => false]);

        $this->actingAs($this->tidakBerhak)
            ->get("/documents/{$document->id}/file")
            ->assertForbidden();
    }

    public function test_unduhan_menghasilkan_404_bila_berkas_hilang(): void
    {
        $document = $this->buatDokumen();
        Storage::disk('local')->delete($document->file_path);

        $this->actingAs($this->berhak)
            ->get("/documents/{$document->id}/file")
            ->assertNotFound();
    }

    // -- Pratinjau ------------------------------------------------------------

    public function test_pratinjau_memakai_content_disposition_inline(): void
    {
        $document = $this->buatDokumen(['file_name_original' => 'laporan.pdf']);

        $this->actingAs($this->berhak)
            ->get("/documents/{$document->id}/preview")
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=laporan.pdf');
    }

    public function test_pratinjau_memakai_proteksi_yang_sama_dengan_unduhan(): void
    {
        // Rute pratinjau yang lebih longgar akan menjadi pintu belakang menuju
        // berkas yang sama persis.
        $document = $this->buatDokumen(['is_shared_to_all' => false]);

        $this->actingAs($this->tidakBerhak)
            ->get("/documents/{$document->id}/preview")
            ->assertForbidden();
    }

    public function test_pratinjau_memakai_pdf_hasil_konversi_bila_tersedia(): void
    {
        $document = $this->buatDokumen([
            'file_name_original' => 'notulen.docx',
            'file_mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'preview_path' => 'previews/2026/08/notulen.pdf',
        ]);
        Storage::disk('local')->put($document->preview_path, '%PDF- berkas hasil konversi');

        $this->actingAs($this->berhak)
            ->get("/documents/{$document->id}/preview")
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=notulen.pdf')
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_gambar_mini_memakai_proteksi_yang_sama_dengan_berkas_asli(): void
    {
        $document = $this->buatDokumen([
            'thumbnail_path' => 'thumbnails/2026/08/dokumen-uji.jpg',
            'is_shared_to_all' => false,
        ]);
        Storage::disk('local')->put($document->thumbnail_path, 'gambar mini uji');

        $this->actingAs($this->tidakBerhak)
            ->get("/documents/{$document->id}/thumbnail")
            ->assertForbidden();

        $this->actingAs($this->pengunggah)
            ->get("/documents/{$document->id}/thumbnail")
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_gambar_mini_tidak_ada_menghasilkan_404(): void
    {
        $document = $this->buatDokumen();

        $this->actingAs($this->berhak)
            ->get("/documents/{$document->id}/thumbnail")
            ->assertNotFound();
    }

    public function test_tamu_tidak_dapat_menyentuh_berkas_sama_sekali(): void
    {
        $document = $this->buatDokumen();

        $this->get("/documents/{$document->id}/file")->assertRedirect(route('login'));
        $this->get("/documents/{$document->id}/preview")->assertRedirect(route('login'));
    }
}
