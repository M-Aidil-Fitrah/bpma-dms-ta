<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class LocaleSwitchingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(User::ROLE_PENGGUNA, 'web');
    }

    public function test_tamu_bergantung_pada_cookie_locale(): void
    {
        $this->get('/login')->assertOk();
        $this->assertSame('id', app()->getLocale());

        $this->withCookie('locale', 'en')->get('/login')->assertOk();
        $this->assertSame('en', app()->getLocale());
    }

    public function test_cookie_locale_yang_tidak_dikenal_diabaikan(): void
    {
        $this->withCookie('locale', 'fr')->get('/login');

        $this->assertSame('id', app()->getLocale());
    }

    public function test_preferensi_akun_diutamakan_daripada_cookie(): void
    {
        $unit = Unit::factory()->create();
        $jabatan = Jabatan::factory()->create();
        $pengguna = User::factory()->create([
            'unit_id' => $unit->id,
            'jabatan_id' => $jabatan->id,
            'locale' => 'en',
        ]);
        $pengguna->assignRole(User::ROLE_PENGGUNA);

        $this->actingAs($pengguna)
            ->withCookie('locale', 'id')
            ->get('/dashboard')
            ->assertOk();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_mengganti_bahasa_menyimpan_preferensi_akun_dan_cookie(): void
    {
        $unit = Unit::factory()->create();
        $jabatan = Jabatan::factory()->create();
        $pengguna = User::factory()->create([
            'unit_id' => $unit->id,
            'jabatan_id' => $jabatan->id,
            'locale' => null,
        ]);
        $pengguna->assignRole(User::ROLE_PENGGUNA);

        $respons = $this->actingAs($pengguna)->put('/locale', ['locale' => 'en']);

        $respons->assertRedirect();
        $respons->assertCookie('locale', 'en');
        $this->assertSame('en', $pengguna->fresh()->locale);
    }

    public function test_bahasa_di_luar_daftar_yang_didukung_ditolak(): void
    {
        $this->put('/locale', ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }
}
