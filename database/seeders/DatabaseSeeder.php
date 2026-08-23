<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Urutannya mengikuti ketergantungan foreign key: data referensi lebih dulu,
 * baru akun, baru dokumen.
 *
 * Setiap seeder baru ditambahkan sebagai satu baris di sini, tanpa menyusun
 * ulang baris yang sudah ada — supaya penambahan oleh anggota tim berbeda tidak
 * saling bertabrakan saat merge.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            JabatanSeeder::class,
            UnitSeeder::class,
            CategorySeeder::class,
            SuperadminSeeder::class,
            UserSeeder::class,
            DocumentSeeder::class,
            DocumentWorkspaceSeeder::class,
        ]);
    }
}
