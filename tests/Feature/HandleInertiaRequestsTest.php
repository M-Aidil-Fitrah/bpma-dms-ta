<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Mengunci kontrak data pengguna yang dibagikan ke seluruh halaman Inertia. */
final class HandleInertiaRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengguna_biasa_menerima_identitas_aman_dan_konteks_organisasi(): void
    {
        Role::findOrCreate(User::ROLE_PENGGUNA, 'web');
        $jabatan = Jabatan::factory()->tingkat(3)->create(['nama' => 'Analis Arsip']);
        $unit = Unit::factory()->create(['nama' => 'Divisi Arsip']);
        $user = User::factory()->create([
            'name' => 'Ayu Kencana',
            'email' => 'ayu.kencana@example.test',
            'jabatan_id' => $jabatan->id,
            'unit_id' => $unit->id,
        ]);
        $user->assignRole(User::ROLE_PENGGUNA);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('auth.user.id', $user->id)
                ->where('auth.user.name', 'Ayu Kencana')
                ->where('auth.user.email', 'ayu.kencana@example.test')
                ->where('auth.user.jabatan', 'Analis Arsip')
                ->where('auth.user.tingkat_akses', 3)
                ->where('auth.user.unit', 'Divisi Arsip')
                ->where('auth.user.is_superadmin', false)
                ->where('auth.user.initials', 'AK')
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token')
                ->has('auth.password_confirmed_until')
                ->where('locale', 'id')
                ->has('flash.id'));
    }

    public function test_superadmin_menerima_penanda_peran_tanpa_data_organisasi_palsu(): void
    {
        Role::findOrCreate(User::ROLE_SUPERADMIN, 'web');
        $superadmin = User::factory()->create(['jabatan_id' => null, 'unit_id' => null]);
        $superadmin->assignRole(User::ROLE_SUPERADMIN);

        $this->actingAs($superadmin)
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.id', $superadmin->id)
                ->where('auth.user.jabatan', null)
                ->where('auth.user.tingkat_akses', null)
                ->where('auth.user.unit', null)
                ->where('auth.user.is_superadmin', true));
    }
}
