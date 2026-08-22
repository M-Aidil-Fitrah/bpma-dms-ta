<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menentukan bahasa aktif untuk permintaan ini (FEAT bilingual).
 *
 * Urutan prioritas: preferensi akun (`users.locale`) lebih dulu — supaya
 * pilihan bahasa ikut pengguna lintas perangkat — baru cookie `locale` untuk
 * tamu yang belum masuk. Nilai yang tidak dikenal diabaikan diam-diam alih-
 * alih membiarkan `App::setLocale()` menerima string sembarangan dari cookie
 * yang bisa saja telah diubah manual.
 *
 * Dipasang SEBELUM `HandleInertiaRequests` di grup middleware `web`: locale
 * harus sudah final sebelum props dibagikan, dan sebelum `<html lang>` di
 * `app.blade.php` dirender.
 */
final class SetLocale
{
    private const LOCALE_TERSEDIA = ['id', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? $request->cookie('locale');

        if (in_array($locale, self::LOCALE_TERSEDIA, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
