<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /**
     * Aksi tulis sensitif memerlukan konfirmasi ulang kata sandi. Sebagian
     * besar tes feature berfokus pada domain masing-masing, sehingga login
     * test-nya dinyatakan sudah melewati step-up authentication. Tes khusus
     * password-confirmation memakai `be()` untuk memulai tanpa penanda ini.
     */
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        $this->withSession(['auth.password_confirmed_at' => time()]);

        return parent::actingAs($user, $guard);
    }

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Penyimpanan berkas dialihkan ke direktori sementara.
         *
         * Tanpa ini, tes menulis ke `storage/app/private` yang sama dengan
         * lingkungan pengembangan — dan seeder dokumen mengosongkan folder itu
         * lebih dulu sebelum mengisi ulang. Akibatnya menjalankan `php artisan
         * test` diam-diam melenyapkan seluruh berkas dokumen milik basis data
         * pengembangan, menyisakan ratusan baris yang menunjuk ke berkas yang
         * tidak ada lagi. Kerusakan semacam itu tidak menampakkan diri sampai
         * seseorang mencoba membuka pratinjau — kemungkinan besar saat demo.
         */
        Storage::fake('local');
    }
}
