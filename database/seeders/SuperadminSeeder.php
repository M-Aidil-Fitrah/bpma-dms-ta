<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Akun Superadmin — satu-satunya jalan masuk pertama ke aplikasi, karena tidak
 * ada registrasi publik (FR-23, FR-24).
 *
 * Nama, surel, dan kata sandinya dibaca dari `.env` dan TIDAK PERNAH ditulis
 * sebagai nilai literal di berkas ini. Konsekuensinya, kredensial tidak ikut
 * masuk riwayat git, dan tiap anggota tim dapat memakai kata sandinya sendiri
 * tanpa menyunting kode.
 *
 * Idempotent: menjalankannya berulang kali memperbarui akun yang sama, tidak
 * pernah menghasilkan Superadmin ganda.
 */
final class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('dms.superadmin.email');
        $password = config('dms.superadmin.password');

        // Gagal dengan lantang, bukan diam-diam membuat akun yang tidak bisa
        // dipakai masuk. Tanpa pemeriksaan ini, `.env` yang belum diisi akan
        // menghasilkan seeding yang "berhasil" tapi aplikasinya terkunci.
        if (blank($email) || blank($password)) {
            throw new RuntimeException(
                'SUPERADMIN_EMAIL dan SUPERADMIN_PASSWORD wajib diisi di .env '
                .'sebelum menjalankan seeder. Lihat README bagian Pemasangan.'
            );
        }

        $superadmin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('dms.superadmin.name'),
                'password' => Hash::make($password),
                // Superadmin berada di luar struktur organisasi: ia melewati
                // seluruh mekanisme akses lewat role, bukan lewat jenjang
                // jabatan atau unit kerja.
                'jabatan_id' => null,
                'unit_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $superadmin->syncRoles([User::ROLE_SUPERADMIN]);
    }
}
