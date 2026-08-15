<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SuperadminProvisioner;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Membuat atau memperbarui akun Superadmin dari `.env`.
 *
 * Perintah tersendiri, bukan hanya bagian dari seeder, karena melayani tiga
 * keadaan yang tidak dapat dijangkau `db:seed`:
 *
 * 1. Memasang aplikasi untuk dipakai sungguhan — Superadmin dibutuhkan, tapi
 *    220 dokumen dummy jelas tidak.
 * 2. Mengganti kata sandi Superadmin: ubah `.env`, jalankan perintah ini.
 * 3. Terkunci di luar aplikasi. Perintah bernama jauh lebih mudah ditemukan
 *    orang yang sedang terdesak daripada `db:seed --class=SuperadminSeeder`,
 *    yang menuntut ia tahu nama kelasnya lebih dulu.
 *
 * Idempotent — dijalankan berkali-kali tidak pernah menghasilkan akun ganda.
 */
final class EnsureSuperadmin extends Command
{
    protected $signature = 'dms:superadmin';

    protected $description = 'Membuat atau memperbarui akun Superadmin dari kredensial di .env';

    public function handle(SuperadminProvisioner $provisioner): int
    {
        try {
            ['user' => $superadmin, 'dibuat' => $dibuat] = $provisioner->provision();
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(
            $dibuat
                ? "Akun Superadmin dibuat: {$superadmin->email}"
                : "Akun Superadmin diperbarui: {$superadmin->email}",
        );

        $this->components->twoColumnDetail('Nama', $superadmin->name);
        $this->components->twoColumnDetail('Surel', $superadmin->email);
        $this->components->twoColumnDetail('Kata sandi', 'diambil dari SUPERADMIN_PASSWORD di .env');

        return self::SUCCESS;
    }
}
