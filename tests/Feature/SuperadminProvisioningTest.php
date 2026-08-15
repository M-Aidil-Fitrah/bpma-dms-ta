<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Penyediaan akun Superadmin (FR-23).
 *
 * Akun ini satu-satunya jalan masuk pertama ke aplikasi — tidak ada registrasi
 * publik. Kalau penyediaannya rusak, aplikasi terkunci total dan tidak ada
 * jalan memperbaikinya dari dalam.
 */
final class SuperadminProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('dms.superadmin.name', 'Administrator BPMA');
        config()->set('dms.superadmin.email', 'superadmin@bpma.internal');
        config()->set('dms.superadmin.password', 'kata-sandi-uji');
    }

    public function test_perintah_membuat_akun_superadmin(): void
    {
        $this->artisan('dms:superadmin')->assertSuccessful();

        $superadmin = User::where('email', 'superadmin@bpma.internal')->first();

        $this->assertNotNull($superadmin);
        $this->assertTrue($superadmin->isSuperadmin());
        $this->assertTrue($superadmin->is_active);
    }

    public function test_perintah_berjalan_di_basis_data_yang_belum_punya_role(): void
    {
        // Justru inilah keadaan saat perintah ini paling dibutuhkan: seseorang
        // terkunci di luar aplikasi pada pemasangan yang belum lengkap.
        $this->assertSame(0, Role::count());

        $this->artisan('dms:superadmin')->assertSuccessful();

        $this->assertTrue(
            User::where('email', 'superadmin@bpma.internal')->first()->isSuperadmin(),
        );
    }

    public function test_dijalankan_berulang_tidak_menggandakan_akun(): void
    {
        $this->artisan('dms:superadmin')->assertSuccessful();
        $this->artisan('dms:superadmin')->assertSuccessful();
        $this->artisan('dms:superadmin')->assertSuccessful();

        $this->assertSame(1, User::where('email', 'superadmin@bpma.internal')->count());
        $this->assertSame(1, User::role(User::ROLE_SUPERADMIN)->count());
    }

    public function test_kata_sandi_diperbarui_mengikuti_environment(): void
    {
        $this->artisan('dms:superadmin')->assertSuccessful();

        config()->set('dms.superadmin.password', 'kata-sandi-baru');
        $this->artisan('dms:superadmin')->assertSuccessful();

        $superadmin = User::where('email', 'superadmin@bpma.internal')->first();

        $this->assertTrue(Hash::check('kata-sandi-baru', $superadmin->password));
        $this->assertFalse(Hash::check('kata-sandi-uji', $superadmin->password));
    }

    public function test_superadmin_berada_di_luar_struktur_organisasi(): void
    {
        $this->artisan('dms:superadmin')->assertSuccessful();

        $superadmin = User::where('email', 'superadmin@bpma.internal')->first();

        // Melewati mekanisme akses lewat role, bukan lewat jenjang jabatan.
        $this->assertNull($superadmin->jabatan_id);
        $this->assertNull($superadmin->unit_id);
        $this->assertTrue($superadmin->bypassesDocumentAccess());
    }

    public function test_perintah_gagal_dengan_pesan_jelas_bila_environment_kosong(): void
    {
        config()->set('dms.superadmin.email', null);

        $this->artisan('dms:superadmin')
            ->expectsOutputToContain('SUPERADMIN_EMAIL')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_kata_sandi_kosong_juga_ditolak(): void
    {
        // Akun tanpa kata sandi akan "berhasil" dibuat tapi tidak bisa dipakai
        // masuk — kegagalan yang paling membingungkan untuk ditelusuri.
        config()->set('dms.superadmin.password', null);

        $this->artisan('dms:superadmin')->assertFailed();

        $this->assertSame(0, User::count());
    }
}
