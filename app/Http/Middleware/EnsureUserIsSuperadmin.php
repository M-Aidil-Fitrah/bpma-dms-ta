<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi modul pengelolaan (pengguna, unit, jabatan, kategori) hanya untuk
 * Superadmin — FR-25 s.d. FR-30.
 *
 * Proteksi berada di sini, bukan sekadar menyembunyikan menu di antarmuka.
 * Menyembunyikan tautan tidak mencegah siapa pun mengetik alamatnya langsung
 * (FR-43).
 */
final class EnsureUserIsSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isSuperadmin() === true, 403);

        return $next($request);
    }
}
