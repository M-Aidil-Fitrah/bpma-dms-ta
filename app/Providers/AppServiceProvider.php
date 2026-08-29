<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') && config('app.debug')) {
            throw new LogicException('APP_DEBUG wajib false saat APP_ENV=production.');
        }

        Vite::prefetch(concurrency: 3);

        if ($this->app->runningInConsole()) {
            // Bawaan `php artisan dev` cuma mendaftar `queue:listen` tanpa
            // `--queue`, sehingga antrean `thumbnail` (gambar mini/pratinjau
            // Office) tidak pernah diproses — gambar mini macet selamanya di
            // "Memproses" meski dev server terlihat berjalan normal.
            // Nama 'queue' menimpa bawaan Laravel (README §Menjalankan Aplikasi).
            DevCommands::artisan('queue:listen --queue=default,thumbnail --timeout=1200', 'queue');

            // Scheduler (perpindahan status Kadaluarsa & purge Sampah) tidak
            // ikut proses bawaan sama sekali — tanpa ini, `composer run dev`
            // terlihat berjalan normal padahal dua command terjadwal itu
            // tidak pernah dieksekusi.
            DevCommands::artisan('schedule:work', 'schedule');
        }
    }
}
