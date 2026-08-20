<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_remember_me_berakhir_dalam_tiga_puluh_hari(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $guard = Auth::guard('web');
        $this->assertInstanceOf(SessionGuard::class, $guard);
        $recaller = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === $guard->getRecallerName());

        $this->assertNotNull($recaller, 'Cookie "Ingat saya" tidak diterbitkan.');
        $this->assertGreaterThanOrEqual(
            time() + (60 * 60 * 24 * 30) - 2,
            $recaller->getExpiresTime(),
            'Cookie "Ingat saya" berakhir terlalu cepat.',
        );
        $this->assertLessThanOrEqual(
            time() + (60 * 60 * 24 * 30) + 2,
            $recaller->getExpiresTime(),
            'Cookie "Ingat saya" melebihi batas 30 hari.',
        );
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_batas_lima_percobaan_mengirim_penanda_toast(): void
    {
        $email = 'terkunci@bpma.test';

        for ($percobaan = 0; $percobaan < 5; $percobaan++) {
            $this->post('/login', ['email' => $email, 'password' => 'keliru']);
        }

        $this->post('/login', ['email' => $email, 'password' => 'keliru'])
            ->assertSessionHasErrors(['email', 'rate_limit']);

        RateLimiter::clear(strtolower($email).'|127.0.0.1');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
