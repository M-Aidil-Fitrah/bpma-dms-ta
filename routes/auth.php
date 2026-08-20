<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

/*
 * FR-24 — Tidak ada registrasi publik.
 *
 * Route `register` bawaan Breeze sengaja dihapus, bukan sekadar disembunyikan
 * dari tampilan. Akun hanya dapat dibuat Superadmin lewat modul Manajemen
 * Pengguna (FR-25). Lihat `PRD.md` §4.5.
 */

/*
 * Reset kata sandi lewat tautan surel (`forgot-password`/`reset-password`
 * bawaan Breeze) sengaja TIDAK diaktifkan. Aplikasi ini tidak pernah
 * mengirim surel apa pun — reset kata sandi selalu manual oleh Superadmin
 * lewat `/admin/users` (lihat `ResetPasswordDialog.tsx`). Mengaktifkan alur
 * bawaan tanpa mailer sungguhan hanya menghasilkan tautan yang tidak pernah
 * sampai ke siapa pun.
 */

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
