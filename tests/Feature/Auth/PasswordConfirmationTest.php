<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/confirm-password');

        $response->assertStatus(200);
    }

    public function test_password_can_be_confirmed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_aksi_sensitif_ditolak_sampai_password_dikonfirmasi(): void
    {
        Role::findOrCreate(User::ROLE_SUPERADMIN, 'web');
        $admin = User::factory()->create();
        $admin->assignRole(User::ROLE_SUPERADMIN);
        $target = User::factory()->create();

        $this->be($admin)
            ->delete("/admin/users/{$target->id}")
            ->assertRedirect(route('password.confirm'));

        $this->withSession([
            'auth.password_confirmed_at' => time() - (int) config('auth.password_timeout') - 1,
        ])
            ->delete("/admin/users/{$target->id}")
            ->assertRedirect(route('password.confirm'));

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete("/admin/users/{$target->id}")
            ->assertRedirect(route('admin.users.index'));
    }

    public function test_konfirmasi_json_mengembalikan_batas_waktu_ke_antarmuka(): void
    {
        $user = User::factory()->create();

        $this->be($user)
            ->postJson('/confirm-password', ['password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['password_confirmed_until']);
    }
}
