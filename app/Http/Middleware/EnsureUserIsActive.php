<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memutus sesi milik akun yang dinonaktifkan (FR-27).
 *
 * Pemeriksaan saat masuk saja tidak cukup. Menonaktifkan akun adalah cara
 * organisasi ini memberhentikan akses seseorang — dan bila yang bersangkutan
 * sedang masuk pada saat itu, sesinya tetap hidup sampai kedaluwarsa sendiri.
 * Dengan "ingat saya" jendela itu berbulan-bulan, dan selama itu ia masih dapat
 * membuka serta mengunduh setiap dokumen yang boleh ia lihat sebelumnya.
 *
 * Karena itu status akun diperiksa pada setiap permintaan, bukan hanya sekali
 * di gerbang masuk.
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if ($pengguna !== null && ! $pengguna->is_active) {
            Auth::guard('web')->logout();

            // Sesi dibuang seluruhnya dan tokennya diperbarui: menyisakan sesi
            // lama berarti menyisakan jalan untuk memakainya kembali.
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Dikirim sebagai `status`, bukan `withErrors`. Halaman masuk
            // merender `status` sebagai pemberitahuan — dan memang itu yang
            // terjadi di sini: bukan surel yang salah ketik, melainkan aksesnya
            // yang dicabut. Menaruhnya di galat kolom surel akan menyesatkan
            // pengguna untuk memperbaiki sesuatu yang tidak keliru.
            return redirect()
                ->route('login')
                ->with('status', 'Akun Anda telah dinonaktifkan. Hubungi administrator sistem.');
        }

        return $next($request);
    }
}
