<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsSuperadmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // `EnsureUserIsActive` diletakkan di grup web, bukan di masing-masing
        // rute. Menempelkannya per rute berarti setiap rute baru harus ingat
        // memasangnya — dan yang terlupa menjadi celah yang tidak terlihat.
        $middleware->web(append: [
            EnsureUserIsActive::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'superadmin' => EnsureUserIsSuperadmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Galat HTML disajikan sebagai komponen Inertia `Error`, bukan halaman
        // Symfony bawaan berbahasa Inggris — supaya bahasa dan tata letaknya
        // sama dengan sisa aplikasi. Saat `APP_DEBUG` aktif (lokal dan CI)
        // seluruh galat dibiarkan mentah agar Ignition dan laporan galat asli
        // tetap terlihat, dan rangkaian tes feature tidak berubah.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            if ($request->expectsJson() || $request->is('api/*') || app()->hasDebugModeEnabled()) {
                return $response;
            }

            $status = $response->getStatusCode();

            // 419 (token kedaluwarsa) bukan halaman tujuan: kembalikan pengguna
            // ke formulir yang sama dengan pesan, supaya ia cukup mengirim ulang.
            if ($status === 419) {
                return back()->with('warning', 'Sesi Anda kedaluwarsa. Muat ulang halaman lalu coba lagi.');
            }

            if (! in_array($status, [403, 404, 429, 500], true)) {
                return $response;
            }

            // Prop bersama disisipkan manual. Galat seperti model 404 dilempar
            // `SubstituteBindings` sebelum `HandleInertiaRequests` sempat
            // membagikannya; URL tak dikenal bahkan tidak melewati grup `web`
            // sama sekali sehingga belum ada sesi. Tanpa `auth` dan `flash`,
            // kerangka React gagal dirender — jadi saat sesi belum ada, dipakai
            // bentuk minimal yang sama dengan `HandleInertiaRequests::share()`.
            $shared = $request->hasSession()
                ? app(HandleInertiaRequests::class)->share($request)
                : [
                    'auth' => ['user' => null, 'password_confirmed_until' => null],
                    'flash' => ['id' => 'error', 'success' => null, 'error' => null, 'warning' => null, 'info' => null],
                    'locale' => app()->getLocale(),
                ];

            return Inertia::render('Error', [
                ...$shared,
                'status' => $status,
                'retryAfter' => $status === 429
                    ? ((int) $response->headers->get('Retry-After') ?: null)
                    : null,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
