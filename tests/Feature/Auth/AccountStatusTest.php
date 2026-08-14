<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Akun yang dinonaktifkan Superadmin tidak boleh dapat masuk (FR-27).
 */
final class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_akun_aktif_dapat_masuk(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_akun_nonaktif_ditolak_meski_kata_sandi_benar(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_pesan_akun_nonaktif_dibedakan_dari_kredensial_salah(): void
    {
        // Pemilik akun perlu tahu masalahnya bukan pada kata sandinya, supaya
        // tidak menghabiskan waktu mencoba menyetel ulang yang sudah benar.
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => 'Akun ini dinonaktifkan. Hubungi administrator sistem.',
        ]);
    }

    public function test_sesi_tidak_tertinggal_setelah_akun_nonaktif_ditolak(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertNull(Auth::user());
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
