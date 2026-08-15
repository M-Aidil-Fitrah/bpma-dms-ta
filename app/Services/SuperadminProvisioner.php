<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Memastikan akun Superadmin ada dan sesuai dengan isi `.env`.
 *
 * Logikanya berdiri di sini, bukan di dalam seeder, karena dibutuhkan dua
 * pemanggil yang sifatnya berbeda: `SuperadminSeeder` saat memasang dari nol,
 * dan perintah `dms:superadmin` saat kata sandi diganti atau seseorang terkunci
 * di luar aplikasi. Menaruhnya di seeder lalu memanggil seeder dari perintah
 * akan mencampur dua peran — seeder seharusnya mengisi data contoh, bukan
 * mengelola akun akar sistem.
 */
final class SuperadminProvisioner
{
    /**
     * @return array{user: User, dibuat: bool}
     */
    public function provision(): array
    {
        [$nama, $email, $kataSandi] = $this->kredensial();

        $sudahAda = User::query()->where('email', $email)->exists();

        $superadmin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $nama,
                'password' => Hash::make($kataSandi),
                // Superadmin berada di luar struktur organisasi: ia melewati
                // seluruh mekanisme akses lewat role, bukan lewat jenjang
                // jabatan atau unit kerja.
                'jabatan_id' => null,
                'unit_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        // Role dipastikan ada lebih dulu, bukan diasumsikan sudah di-seed.
        // Tanpa ini, menjalankan `dms:superadmin` pada basis data yang belum
        // pernah di-seed berakhir dengan galat Spatie "There is no role named
        // superadmin" — pesan yang tidak berarti apa-apa bagi orang yang
        // menjalankannya justru karena sedang terkunci di luar aplikasi.
        Role::findOrCreate(User::ROLE_SUPERADMIN, 'web');

        $superadmin->syncRoles([User::ROLE_SUPERADMIN]);

        return ['user' => $superadmin, 'dibuat' => ! $sudahAda];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function kredensial(): array
    {
        $nama = (string) config('dms.superadmin.name');
        $email = config('dms.superadmin.email');
        $kataSandi = config('dms.superadmin.password');

        // Gagal dengan lantang, bukan diam-diam membuat akun yang tidak bisa
        // dipakai masuk. Tanpa pemeriksaan ini, `.env` yang belum diisi
        // menghasilkan proses yang "berhasil" tapi aplikasinya terkunci.
        if (blank($email) || blank($kataSandi)) {
            throw new RuntimeException(
                'SUPERADMIN_EMAIL dan SUPERADMIN_PASSWORD wajib diisi di .env '
                .'sebelum akun Superadmin dapat dibuat. Lihat README bagian Pemasangan.'
            );
        }

        return [$nama, (string) $email, (string) $kataSandi];
    }
}
