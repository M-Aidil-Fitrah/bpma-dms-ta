<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Role sistem — tetap dua, tidak pernah bertambah (FR-30).
 *
 * Jenjang jabatan dan unit kerja sengaja BUKAN role, melainkan atribut pada
 * akun. Menjadikan tiap jabatan sebagai role akan memaksa penambahan role
 * setiap kali struktur organisasi berubah (`PRD.md` §2.1).
 */
final class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([User::ROLE_SUPERADMIN, User::ROLE_PENGGUNA] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
