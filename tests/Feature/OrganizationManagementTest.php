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

/** CRUD organisasi FEAT-14 — soft-disable, bukan penghapusan baris. */
final class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $anggota;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([User::ROLE_SUPERADMIN, User::ROLE_PENGGUNA] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $jabatan = Jabatan::factory()->tingkat(4)->create();
        $unit = Unit::factory()->create();

        $this->superadmin = User::factory()->create(['jabatan_id' => null, 'unit_id' => null]);
        $this->superadmin->assignRole(User::ROLE_SUPERADMIN);

        $this->anggota = User::factory()->create(['jabatan_id' => $jabatan->id, 'unit_id' => $unit->id]);
        $this->anggota->assignRole(User::ROLE_PENGGUNA);
    }

    public function test_tamu_dialihkan_dan_pengguna_biasa_ditolak_dari_semua_resource_organisasi(): void
    {
        foreach (['jabatans', 'units', 'categories', 'settings'] as $resource) {
            $this->get("/admin/{$resource}")->assertRedirect(route('login'));
        }

        foreach (['jabatans', 'units', 'categories', 'settings'] as $resource) {
            $this->actingAs($this->anggota)->get("/admin/{$resource}")->assertForbidden();
        }
    }

    public function test_superadmin_dapat_membuka_tiga_daftar_organisasi(): void
    {
        $this->actingAs($this->superadmin);

        $this->get('/admin/jabatans')->assertInertia(fn (AssertableInertia $page) => $page->component('Positions/Index'));
        $this->get('/admin/units')->assertInertia(fn (AssertableInertia $page) => $page->component('Units/Index'));
        $this->get('/admin/categories')->assertInertia(fn (AssertableInertia $page) => $page->component('Categories/Index'));
    }

    public function test_jumlah_query_daftar_kategori_tidak_bertambah_seiring_data(): void
    {
        $this->actingAs($this->superadmin);
        Category::factory()->count(3)->create();

        // Permintaan pertama turut memanaskan autentikasi dan cache role.
        $this->hitungQueryDaftarKategori();

        $queryDenganSedikitKategori = $this->hitungQueryDaftarKategori();

        Category::factory()->count(40)->create();

        $queryDenganBanyakKategori = $this->hitungQueryDaftarKategori();

        $this->assertSame($queryDenganSedikitKategori, $queryDenganBanyakKategori);
    }

    private function hitungQueryDaftarKategori(): int
    {
        $jumlahQuery = 0;

        DB::listen(static function () use (&$jumlahQuery): void {
            $jumlahQuery++;
        });

        $this->get('/admin/categories')->assertOk();

        return $jumlahQuery;
    }

    public function test_superadmin_dapat_menambah_dan_mengubah_jabatan(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/admin/jabatans', ['nama' => 'Analis Utama', 'tingkat_akses' => 3])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $jabatan = Jabatan::firstWhere('nama', 'Analis Utama');
        $this->assertNotNull($jabatan);
        $this->assertSame(3, $jabatan->tingkat_akses);

        $this->patch("/admin/jabatans/{$jabatan->id}", ['nama' => 'Analis Madya', 'tingkat_akses' => 4])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('jabatans', ['id' => $jabatan->id, 'nama' => 'Analis Madya', 'tingkat_akses' => 4]);
    }

    public function test_nama_jabatan_harus_unik(): void
    {
        $jabatan = Jabatan::factory()->create(['nama' => 'Jabatan Tunggal']);

        $this->actingAs($this->superadmin)
            ->post('/admin/jabatans', ['nama' => $jabatan->nama, 'tingkat_akses' => 4])
            ->assertSessionHasErrors('nama');
    }

    public function test_menonaktifkan_jabatan_yang_dipakai_tidak_merusak_pengguna_lama(): void
    {
        $jabatan = $this->anggota->jabatan;

        $this->actingAs($this->superadmin)
            ->delete("/admin/jabatans/{$jabatan->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($jabatan->fresh()->is_active);
        $this->assertSame($jabatan->id, $this->anggota->fresh()->jabatan_id);
    }

    public function test_superadmin_dapat_menambah_unit_dan_menampilkan_induknya(): void
    {
        $induk = Unit::factory()->tingkatAtas()->create(['nama' => 'Deputi Uji']);

        $this->actingAs($this->superadmin)
            ->post('/admin/units', ['nama' => 'Divisi Uji', 'parent_id' => $induk->id, 'tipe' => Unit::TIPE_DIVISI])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->get('/admin/units')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('referensi.data.2.nama', 'Divisi Uji')
                ->where('referensi.data.2.kedalaman', 1));
    }

    public function test_unit_tidak_boleh_menjadi_induk_dirinya_sendiri_atau_keturunannya(): void
    {
        $akar = Unit::factory()->tingkatAtas()->create();
        $anak = Unit::factory()->dibawah($akar)->create();
        $cucu = Unit::factory()->dibawah($anak)->create();

        $this->actingAs($this->superadmin)
            ->patch("/admin/units/{$akar->id}", ['nama' => $akar->nama, 'parent_id' => $akar->id, 'tipe' => $akar->tipe])
            ->assertSessionHasErrors('parent_id');

        $this->patch("/admin/units/{$akar->id}", ['nama' => $akar->nama, 'parent_id' => $cucu->id, 'tipe' => $akar->tipe])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($akar->fresh()->parent_id);
    }

    public function test_unit_nonaktif_tidak_dapat_dipilih_menjadi_induk_baru(): void
    {
        $nonaktif = Unit::factory()->tingkatAtas()->nonaktif()->create();

        $this->actingAs($this->superadmin)
            ->post('/admin/units', ['nama' => 'Unit Baru', 'parent_id' => $nonaktif->id, 'tipe' => Unit::TIPE_DIVISI])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_menonaktifkan_unit_yang_dipakai_dokumen_tetap_menjaga_semua_relasi(): void
    {
        $unit = Unit::factory()->create();
        $category = Category::factory()->create();
        $document = Document::factory()->create([
            'category_id' => $category->id,
            'origin_unit_id' => $unit->id,
            'uploaded_by' => $this->anggota->id,
        ]);
        $document->targetUnits()->attach($unit->id, ['added_by' => $this->superadmin->id]);

        $this->actingAs($this->superadmin)
            ->delete("/admin/units/{$unit->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($unit->fresh()->is_active);
        $this->assertSame($unit->id, $document->fresh()->origin_unit_id);
        $this->assertDatabaseHas('document_units', ['document_id' => $document->id, 'unit_id' => $unit->id]);
    }

    public function test_superadmin_dapat_menambah_mengubah_dan_menonaktifkan_kategori_terpakai(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/admin/categories', ['nama' => 'Arsip Kontrak', 'deskripsi' => 'Kontrak pengadaan dan kerja sama.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $category = Category::firstWhere('nama', 'Arsip Kontrak');
        $this->assertNotNull($category);

        $this->patch("/admin/categories/{$category->id}", ['nama' => 'Arsip Perjanjian', 'deskripsi' => 'Diperbarui.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Document::factory()->create(['category_id' => $category->id, 'uploaded_by' => $this->anggota->id]);

        $this->delete("/admin/categories/{$category->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($category->fresh()->is_active);
        $this->assertDatabaseHas('documents', ['category_id' => $category->id]);
    }

    public function test_daftar_organisasi_dapat_dicari_dan_difilter_statusnya(): void
    {
        Category::factory()->create(['nama' => 'Kategori Yang Dicari']);
        Category::factory()->nonaktif()->create(['nama' => 'Kategori Nonaktif']);

        $this->actingAs($this->superadmin)
            ->get('/admin/categories?cari=dicari')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('referensi.data', 1));

        $this->get('/admin/categories?status=nonaktif')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('referensi.data', 1));
    }

    public function test_setiap_resource_organisasi_dapat_diaktifkan_kembali(): void
    {
        $jabatan = Jabatan::factory()->create(['is_active' => false]);
        $unit = Unit::factory()->create(['is_active' => false]);
        $category = Category::factory()->create(['is_active' => false]);

        $this->actingAs($this->superadmin);
        $this->patch("/admin/jabatans/{$jabatan->id}/restore")->assertSessionHasNoErrors();
        $this->patch("/admin/units/{$unit->id}/restore")->assertSessionHasNoErrors();
        $this->patch("/admin/categories/{$category->id}/restore")->assertSessionHasNoErrors();

        $this->assertTrue($jabatan->fresh()->is_active);
        $this->assertTrue($unit->fresh()->is_active);
        $this->assertTrue($category->fresh()->is_active);
    }
}
