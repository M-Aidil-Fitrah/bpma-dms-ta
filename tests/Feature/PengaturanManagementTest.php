<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\Unit;
use App\Models\User;
use App\Services\PengaturanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Setelan Superadmin harus memengaruhi aplikasi, bukan hanya tersimpan. */
final class PengaturanManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate(User::ROLE_SUPERADMIN, 'web');
        Role::findOrCreate(User::ROLE_PENGGUNA, 'web');

        $this->superadmin = User::factory()->create(['jabatan_id' => null, 'unit_id' => null]);
        $this->superadmin->assignRole(User::ROLE_SUPERADMIN);
    }

    public function test_superadmin_dapat_menyimpan_kedua_pengaturan_yang_diizinkan(): void
    {
        $this->actingAs($this->superadmin)
            ->patch('/admin/settings', [
                'unggah_batas_kb' => 2048,
                'dokumen_per_halaman' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $service = app(PengaturanService::class);
        $this->assertSame(2048, $service->integer('unggah.batas_kb'));
        $this->assertSame(10, $service->integer('dokumen.per_halaman'));
        $this->assertDatabaseHas('pengaturan', ['kunci' => 'dokumen.per_halaman', 'diubah_oleh' => $this->superadmin->id]);
    }

    public function test_pengaturan_tidak_menerima_nilai_di_luar_batasnya(): void
    {
        $this->actingAs($this->superadmin)
            ->patch('/admin/settings', [
                'unggah_batas_kb' => 100,
                'dokumen_per_halaman' => 13,
            ])
            ->assertSessionHasErrors(['unggah_batas_kb', 'dokumen_per_halaman']);
    }

    public function test_superadmin_dapat_memilih_batas_unggah_sampai_batas_infrastruktur(): void
    {
        $batasTertinggi = (int) config('dms.dokumen.ukuran_tertinggi_kb');

        $this->actingAs($this->superadmin)
            ->patch('/admin/settings', ['unggah_batas_kb' => $batasTertinggi])
            ->assertSessionHasNoErrors();

        $this->assertSame($batasTertinggi, app(PengaturanService::class)->integer('unggah.batas_kb'));

        $this->actingAs($this->superadmin)
            ->patch('/admin/settings', ['unggah_batas_kb' => $batasTertinggi + 1])
            ->assertSessionHasErrors('unggah_batas_kb');
    }

    public function test_nilai_kosong_menghapus_override_dan_kembali_ke_bawaan(): void
    {
        $service = app(PengaturanService::class);
        $service->simpan('dokumen.per_halaman', 10, $this->superadmin->id);

        $this->actingAs($this->superadmin)
            ->patch('/admin/settings', [
                'unggah_batas_kb' => null,
                'dokumen_per_halaman' => null,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('pengaturan', ['kunci' => 'dokumen.per_halaman']);
        $this->assertSame((int) config('dms.dokumen.per_halaman'), $service->integer('dokumen.per_halaman'));
    }

    /**
     * Rentang evaluasi tidak lagi dapat diubah Superadmin (dihapus dari
     * Settings) — kunci yang tidak ada di PengaturanService::DIIZINKAN
     * senyap diabaikan simpan(), bukan galat, sehingga dasbor tetap memakai
     * bawaan config apa pun yang dikirim di sini.
     */
    public function test_pengaturan_rentang_evaluasi_tidak_lagi_dapat_diubah(): void
    {
        app(PengaturanService::class)->simpan('dokumen.rentang_evaluasi_awal', 7, $this->superadmin->id);

        $this->assertDatabaseMissing('pengaturan', ['kunci' => 'dokumen.rentang_evaluasi_awal']);

        $this->actingAs($this->superadmin)->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('data.rentang_evaluasi', (int) config('dms.dokumen.rentang_evaluasi_awal')));
    }

    public function test_pengaturan_pagination_dipakai_daftar_dokumen(): void
    {
        $service = app(PengaturanService::class);
        $service->simpan('dokumen.per_halaman', 10, $this->superadmin->id);
        $category = Category::factory()->create();
        $unit = Unit::factory()->create();

        Document::factory()->count(11)->dibagikanKeSemua()->create([
            'category_id' => $category->id,
            'origin_unit_id' => $unit->id,
            'uploaded_by' => $this->superadmin->id,
        ]);

        $this->actingAs($this->superadmin)->get('/documents')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dokumen.per_page', 10)
                ->has('dokumen.data', 10));
    }
}
