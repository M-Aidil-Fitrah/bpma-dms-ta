<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mengganti bahasa aktif (FEAT bilingual).
 *
 * Berlaku untuk tamu maupun pengguna yang sudah masuk — sengaja tidak
 * dipasang di belakang middleware `auth`, karena halaman masuk sendiri butuh
 * pemilih bahasa. Bagi tamu, pilihannya hanya bertahan lewat cookie; bagi
 * pengguna yang sudah masuk, disalin ke `users.locale` juga supaya ikut
 * berpindah perangkat.
 */
final class LocaleController extends Controller
{
    private const LOCALE_TERSEDIA = ['id', 'en'];

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(self::LOCALE_TERSEDIA)],
        ]);

        $request->user()?->update(['locale' => $data['locale']]);

        return back()->withCookie(
            cookie('locale', $data['locale'], 60 * 24 * 365),
        );
    }
}
