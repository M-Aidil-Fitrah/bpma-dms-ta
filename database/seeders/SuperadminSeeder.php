<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\SuperadminProvisioner;
use Illuminate\Database\Seeder;

/**
 * Akun Superadmin — satu-satunya jalan masuk pertama ke aplikasi, karena tidak
 * ada registrasi publik (FR-23, FR-24).
 *
 * Tetap dipanggil `DatabaseSeeder` supaya `migrate --seed` sekali jalan
 * menghasilkan aplikasi yang langsung dapat dimasuki. Mengeluarkannya dari sana
 * berarti menambah satu langkah yang bisa terlupa — dan yang terlupa itu
 * menghasilkan aplikasi terkunci total tanpa petunjuk apa pun.
 *
 * Logikanya sendiri berada di `SuperadminProvisioner`, dipakai bersama perintah
 * `php artisan dms:superadmin` untuk keadaan di luar pemasangan awal:
 * penggantian kata sandi, dan pemasangan sungguhan yang tidak menghendaki data
 * dummy ikut terbawa.
 */
final class SuperadminSeeder extends Seeder
{
    public function run(SuperadminProvisioner $provisioner): void
    {
        $provisioner->provision();
    }
}
