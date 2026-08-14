<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aplikasi ini internal — tidak ada halaman publik (`PRD.md` §1).
 * Akar situs hanya berperan sebagai pengalih.
 */
final class RootRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_dialihkan_ke_halaman_masuk(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_pengguna_yang_sudah_masuk_dialihkan_ke_dasbor(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }

    public function test_registrasi_publik_tidak_tersedia(): void
    {
        // FR-24 — akun hanya dibuat Superadmin, tidak ada pendaftaran mandiri.
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }
}
