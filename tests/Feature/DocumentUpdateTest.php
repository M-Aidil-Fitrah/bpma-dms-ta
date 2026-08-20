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
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ubah, nonaktifkan & kelola akses (FR-08, FR-08b, FR-10, FR-42).
 *
 * Yang diuji di sini bukan sekadar "kolomnya berubah", melainkan akibatnya pada
 * siapa yang dapat melihat dokumen — karena itulah satu-satunya hal yang
 * benar-benar penting pada tabel akses.
 */
final class DocumentUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $pemilik;

    private Unit $deputi;

    private Unit $divisi;

    private Category $kategori;

    private Document $dokumen;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(User::ROLE_PENGGUNA, 'web');
        Role::findOrCreate(User::ROLE_SUPERADMIN, 'web');

        $jabatan = Jabatan::factory()->tingkat(4)->create();
        $this->deputi = Unit::factory()->tingkatAtas()->create();
        $this->divisi = Unit::factory()->dibawah($this->deputi)->create();
        $this->kategori = Category::factory()->create();

        $this->pemilik = $this->buatPengguna($this->divisi);

        $this->dokumen = Document::factory()->create([
            'uploaded_by' => $this->pemilik->id,
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->divisi->id,
            'edit_scope' => DocumentEditScope::OwnerOnly,
            'is_shared_to_all' => false,
            'min_tingkat_akses' => null,
            'is_active' => true,
        ]);
        $this->dokumen->targetUnits()->attach([
            $this->divisi->id => ['added_by' => $this->pemilik->id],
        ]);
    }

    private function buatPengguna(?Unit $unit, int $tingkat = 4, bool $superadmin = false): User
    {
        $pengguna = User::factory()->create([
            'jabatan_id' => Jabatan::factory()->tingkat($tingkat)->create()->id,
            'unit_id' => $unit?->id,
        ]);
        $pengguna->assignRole($superadmin ? User::ROLE_SUPERADMIN : User::ROLE_PENGGUNA);

        return $pengguna;
    }

    /**
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function formulir(array $ubah = []): array
    {
        return [
            'nomor' => $this->dokumen->nomor,
            'judul' => $this->dokumen->judul,
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->divisi->id,
            'tanggal' => $this->dokumen->tanggal->toDateString(),
            'edit_scope' => DocumentEditScope::OwnerOnly->value,
            'unit_ids' => [$this->divisi->id],
            'version_note' => 'Revisi metadata untuk pengujian.',
            ...$ubah,
        ];
    }

    private function versiTerbaru(): Document
    {
        return Document::query()
            ->where('version_root_id', $this->dokumen->version_root_id)
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->firstOrFail();
    }

    // -- Akibat perubahan akses ------------------------------------------------

    public function test_menambah_satu_orang_membuatnya_langsung_dapat_melihat(): void
    {
        $orangLuar = $this->buatPengguna(Unit::factory()->tingkatAtas()->create());

        $this->assertFalse($orangLuar->can('view', $this->dokumen));

        $this->actingAs($this->pemilik)
            ->patch("/documents/{$this->dokumen->id}", $this->formulir([
                'shared_user_ids' => [$orangLuar->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertTrue(
            $orangLuar->fresh()->can('view', $this->versiTerbaru()),
            'Orang yang baru ditambahkan belum dapat melihat dokumen.',
        );
    }

    public function test_menghapus_satu_unit_langsung_mencabut_akses_anggotanya(): void
    {
        $anggota = $this->buatPengguna($this->divisi);

        $this->assertTrue($anggota->can('view', $this->dokumen));

        // Jenjang ini harus benar-benar ada di tabel jabatan, kalau tidak
        // permintaannya tertahan validasi dan tesnya lulus palsu.
        Jabatan::factory()->tingkat(1)->create();

        // Unit dibuang; tinggal satu mekanisme lain supaya tidak tertahan
        // aturan "minimal satu mekanisme aktif".
        $this->actingAs($this->pemilik)
            ->patch("/documents/{$this->dokumen->id}", $this->formulir([
                'unit_ids' => [],
                'min_tingkat_akses' => 1,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertFalse(
            $anggota->fresh()->can('view', $this->versiTerbaru()),
            'Anggota unit yang dibuang masih dapat melihat dokumen.',
        );
    }

    public function test_jejak_pemberi_akses_lama_tidak_ditimpa_penyunting_baru(): void
    {
        // Kalau `added_by` ikut ditulis ulang setiap kali dokumen disunting,
        // catatan siapa yang mula-mula membuka akses hilang — dan itulah satu-
        // satunya hal yang dicari ketika menelusuri kebocoran dokumen.
        $penyunting = $this->buatPengguna($this->divisi, superadmin: true);
        $unitBaru = Unit::factory()->tingkatAtas()->create();

        $this->actingAs($penyunting)
            ->patch("/documents/{$this->dokumen->id}", $this->formulir([
                'unit_ids' => [$this->divisi->id, $unitBaru->id],
            ]));

        $arsip = $this->dokumen->fresh()->targetUnits->keyBy('id');
        $terlampir = $this->versiTerbaru()->targetUnits->keyBy('id');

        $this->assertSame(
            $this->pemilik->id,
            $arsip[$this->divisi->id]->pivot->added_by,
            'Jejak pemberi akses pada arsip ikut tertimpa.',
        );
        $this->assertSame(
            $penyunting->id,
            $terlampir[$this->divisi->id]->pivot->added_by,
        );
        $this->assertSame(
            $penyunting->id,
            $terlampir[$unitBaru->id]->pivot->added_by,
        );
    }

    public function test_dokumen_tidak_boleh_kehilangan_seluruh_mekanisme_akses(): void
    {
        $this->actingAs($this->pemilik)
            ->patch("/documents/{$this->dokumen->id}", $this->formulir([
                'unit_ids' => [],
            ]))
            ->assertSessionHasErrors('akses');

        $this->assertCount(1, $this->dokumen->fresh()->targetUnits);
    }

    public function test_unit_nonaktif_tidak_dapat_ditambahkan_saat_menyunting(): void
    {
        $nonaktif = Unit::factory()->nonaktif()->create();

        $this->actingAs($this->pemilik)
            ->patch("/documents/{$this->dokumen->id}", $this->formulir([
                'unit_ids' => [$this->divisi->id, $nonaktif->id],
            ]))
            ->assertSessionHasErrors('unit_ids.1');
    }

    // -- Berkas tidak dapat diganti -------------------------------------------

    public function test_berkas_tidak_dapat_diganti_lewat_formulir_ubah(): void
    {
        $jalurAsli = $this->dokumen->file_path;

        $this->actingAs($this->pemilik)
            ->patch("/documents/{$this->dokumen->id}", $this->formulir([
                'file' => UploadedFile::fake()->create('lain.pdf', 10),
            ]))
            ->assertSessionHasErrors('file');

        $this->assertSame($jalurAsli, $this->dokumen->fresh()->file_path);
    }

    // -- Wewenang menyunting (FR-08b) -----------------------------------------

    public function test_owner_only_hanya_dapat_disunting_pemiliknya(): void
    {
        $anggota = $this->buatPengguna($this->divisi);

        $this->actingAs($anggota)
            ->patch("/documents/{$this->dokumen->id}", $this->formulir(['judul' => 'Dibajak']))
            ->assertForbidden();

        $this->assertNotSame('Dibajak', $this->dokumen->fresh()->judul);
    }

    public function test_match_visibility_dapat_disunting_siapa_pun_yang_berhak_melihat(): void
    {
        $this->dokumen->update(['edit_scope' => DocumentEditScope::MatchVisibility]);
        $anggota = $this->buatPengguna($this->divisi);

        $this->actingAs($anggota)
            ->patch("/documents/{$this->dokumen->id}", $this->formulir([
                'judul' => 'Disunting Rekan Seunit',
                'edit_scope' => DocumentEditScope::MatchVisibility->value,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('Disunting Rekan Seunit', $this->versiTerbaru()->judul);
    }

    public function test_orang_tanpa_hak_melihat_tidak_dapat_menyunting_match_visibility(): void
    {
        $this->dokumen->update(['edit_scope' => DocumentEditScope::MatchVisibility]);
        $orangLuar = $this->buatPengguna(Unit::factory()->tingkatAtas()->create());

        $this->actingAs($orangLuar)
            ->patch("/documents/{$this->dokumen->id}", $this->formulir(['judul' => 'Dibajak']))
            ->assertForbidden();
    }

    public function test_formulir_ubah_tertutup_bagi_yang_tidak_berhak(): void
    {
        // Halaman formulirnya sendiri, bukan hanya aksi simpannya — tautan
        // langsung ke `/edit` tidak boleh menampilkan isi dokumen.
        $anggota = $this->buatPengguna($this->divisi);

        $this->actingAs($anggota)
            ->get("/documents/{$this->dokumen->id}/edit")
            ->assertForbidden();
    }

    public function test_formulir_ubah_terbuka_dengan_akses_yang_sedang_berlaku(): void
    {
        // Formulir yang terbuka kosong akan membuat penyunting yang hanya ingin
        // memperbaiki judul tanpa sadar mencabut seluruh daftar aksesnya.
        $this->actingAs($this->pemilik)
            ->get("/documents/{$this->dokumen->id}/edit")
            ->assertOk()
            ->assertInertia(
                fn ($halaman) => $halaman
                    ->component('Documents/Edit')
                    ->where('dokumen.unit_ids', [$this->divisi->id])
                    ->where('dokumen.judul', $this->dokumen->judul),
            );
    }

    // -- Sampah dan pemulihan (FEAT-24) ----------------------------------------

    public function test_memindahkan_ke_sampah_menyembunyikan_dokumen_tanpa_menghapus_barisnya(): void
    {
        $this->actingAs($this->pemilik)
            ->delete("/documents/{$this->dokumen->id}")
            ->assertRedirect(route('documents.trash'));

        $this->assertDatabaseHas('documents', [
            'id' => $this->dokumen->id,
            'is_active' => true,
        ]);
        $this->assertNotNull(Document::query()->findOrFail($this->dokumen->id)->trashed_at);
    }

    public function test_dokumen_nonaktif_hilang_dari_daftar(): void
    {
        $this->dokumen->update(['is_active' => false]);

        $this->actingAs($this->pemilik)
            ->get('/documents')
            ->assertOk()
            ->assertInertia(fn ($halaman) => $halaman->where('dokumen.total', 0));
    }

    public function test_dokumen_nonaktif_tidak_dapat_dibuka_lewat_alamat_langsung(): void
    {
        // Menyembunyikan dari daftar saja tidak cukup — tautan lama masih
        // tersimpan di riwayat peramban dan di surel (FR-43).
        $this->dokumen->update(['is_active' => false]);

        $this->actingAs($this->pemilik)
            ->get("/documents/{$this->dokumen->id}")
            ->assertForbidden();

        $this->actingAs($this->pemilik)
            ->get("/documents/{$this->dokumen->id}/file")
            ->assertForbidden();

        $this->actingAs($this->pemilik)
            ->get("/documents/{$this->dokumen->id}/preview")
            ->assertForbidden();
    }

    public function test_pemilik_dapat_membuka_versi_lama_dari_dokumen_pengganti_yang_masih_berlaku(): void
    {
        $versiBaru = Document::factory()->create([
            'uploaded_by' => $this->pemilik->id,
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->divisi->id,
            'replaces_document_id' => $this->dokumen->id,
            'is_shared_to_all' => false,
            'min_tingkat_akses' => null,
            'is_active' => true,
        ]);
        $versiBaru->targetUnits()->attach($this->divisi->id, ['added_by' => $this->pemilik->id]);
        $this->dokumen->update(['is_active' => false]);

        $this->actingAs($this->pemilik)
            ->get("/documents/{$this->dokumen->id}")
            ->assertOk()
            ->assertInertia(fn ($halaman) => $halaman
                ->where('dokumen.versi_berikutnya_id', $versiBaru->id)
                ->where('dokumen.judul_versi_berikutnya', $versiBaru->judul));

        $this->actingAs($this->pemilik)
            ->get("/documents/{$versiBaru->id}")
            ->assertOk()
            ->assertInertia(fn ($halaman) => $halaman
                ->where('dokumen.versi_sebelumnya_id', $this->dokumen->id)
                ->where('dokumen.judul_versi_sebelumnya', $this->dokumen->judul));
    }

    public function test_dokumen_nonaktif_tidak_dapat_disunting(): void
    {
        $this->dokumen->update(['is_active' => false]);

        $this->actingAs($this->pemilik)
            ->patch("/documents/{$this->dokumen->id}", $this->formulir(['judul' => 'Diubah']))
            ->assertForbidden();
    }

    public function test_superadmin_tetap_dapat_membuka_dan_mengaktifkan_kembali(): void
    {
        // Tanpa ini, dokumen yang keliru dinonaktifkan menjadi mustahil
        // dipulihkan lewat antarmuka.
        $this->dokumen->update(['is_active' => false]);
        $superadmin = $this->buatPengguna(null, tingkat: 1, superadmin: true);

        $this->actingAs($superadmin)
            ->get("/documents/{$this->dokumen->id}")
            ->assertOk();

        $this->actingAs($superadmin)
            ->patch("/documents/{$this->dokumen->id}/restore")
            ->assertRedirect();

        $this->assertTrue($this->dokumen->fresh()->is_active);
    }

    public function test_pengguna_biasa_tidak_dapat_mengaktifkan_kembali(): void
    {
        $this->dokumen->update(['is_active' => false]);

        $this->actingAs($this->pemilik)
            ->patch("/documents/{$this->dokumen->id}/restore")
            ->assertForbidden();

        $this->assertFalse($this->dokumen->fresh()->is_active);
    }

    public function test_tamu_tidak_dapat_menyentuh_aksi_apa_pun(): void
    {
        $this->patch("/documents/{$this->dokumen->id}", $this->formulir())
            ->assertRedirect(route('login'));
        $this->delete("/documents/{$this->dokumen->id}")->assertRedirect(route('login'));
        $this->get("/documents/{$this->dokumen->id}/edit")->assertRedirect(route('login'));

        $this->assertTrue($this->dokumen->fresh()->is_active);
    }
}
