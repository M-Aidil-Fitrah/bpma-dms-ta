<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute Aplikasi
|--------------------------------------------------------------------------
|
| Aplikasi ini bersifat internal (`PRD.md` §1) — tidak ada halaman publik.
| Akar situs langsung mengalihkan ke dasbor bagi yang sudah masuk, atau ke
| halaman masuk bagi yang belum.
|
| Setiap modul menulis rutenya di bloknya sendiri di bawah, supaya penambahan
| rute oleh anggota tim yang berbeda tidak saling bertabrakan saat merge.
| Lihat `Rencana-Sprint.md` §4.3.
|
*/

Route::get('/', static fn () => redirect()->route(
    auth()->check() ? 'dashboard' : 'login'
));

/*
|--------------------------------------------------------------------------
| Modul: Dasbor — FEAT-06
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Modul: Dokumen — FEAT-07, FEAT-08
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function (): void {
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');

    // Unduh dan pratinjau memakai proteksi Policy yang sama persis; bedanya
    // hanya header `Content-Disposition` (FR-09, FR-09b).
    Route::get('/documents/{document}/file', [DocumentController::class, 'serveFile'])
        ->name('documents.file');
    Route::get('/documents/{document}/preview', [DocumentController::class, 'previewFile'])
        ->name('documents.preview');
});

/*
|--------------------------------------------------------------------------
| Modul: Profil Pengguna — bawaan Breeze
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // Tanpa rute hapus akun — lihat catatan di ProfileController.
});

require __DIR__.'/auth.php';
