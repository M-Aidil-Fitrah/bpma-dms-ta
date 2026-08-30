<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Galat HTML (403/404/429/500) disajikan sebagai komponen Inertia `Error`
 * saat `APP_DEBUG` mati — bukan halaman Symfony bawaan berbahasa Inggris.
 *
 * Kontrak sebaliknya (debug aktif = galat mentah) dijaga oleh seluruh tes
 * feature lain yang memanggil `assertForbidden()` / `assertNotFound()`:
 * bila closure di `bootstrap/app.php` ikut mengubah respons saat debug aktif,
 * tes-tes itu akan gagal.
 */
final class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(User::ROLE_PENGGUNA, 'web');

        $this->pengguna = User::factory()->create();
        $this->pengguna->assignRole(User::ROLE_PENGGUNA);

        // Tanpa ini, closure `respond()` mengembalikan respons apa adanya —
        // sengaja, supaya Ignition tetap terlihat saat pengembangan.
        config()->set('app.debug', false);
    }

    public function test_akses_ditolak_dirender_sebagai_komponen_inertia(): void
    {
        $this->actingAs($this->pengguna)
            ->get('/admin/users')
            ->assertForbidden()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Error')
                    ->where('status', 403)
                    ->where('retryAfter', null)
                    // Prop bersama harus ikut — kerangka React membacanya.
                    ->has('auth')
                    ->has('locale'),
            );
    }

    public function test_model_tidak_ditemukan_dirender_dengan_prop_bersama(): void
    {
        // `SubstituteBindings` melempar sebelum `HandleInertiaRequests` sempat
        // membagikan prop; halaman galat tetap harus menerima `auth`.
        $this->actingAs($this->pengguna)
            ->get('/documents/999999')
            ->assertNotFound()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Error')
                    ->where('status', 404)
                    ->has('auth'),
            );
    }

    public function test_url_tak_dikenal_dirender_sebagai_komponen_inertia(): void
    {
        $this->actingAs($this->pengguna)
            ->get('/rute-yang-tidak-pernah-ada')
            ->assertNotFound()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Error')
                    ->where('status', 404)
                    ->has('auth')
                    ->has('flash'),
            );
    }

    public function test_metode_tidak_diizinkan_tetap_405_bukan_404(): void
    {
        // Path terdaftar, metode salah: 405 lebih tepat daripada 404, dan
        // dibiarkan mentah — halaman Inertia hanya untuk 403/404/429/500.
        $this->actingAs($this->pengguna)
            ->delete('/profile')
            ->assertStatus(405);
    }

    public function test_permintaan_json_tetap_menerima_respons_json_bukan_halaman_inertia(): void
    {
        $this->actingAs($this->pengguna)
            ->getJson('/documents/999999')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }
}
