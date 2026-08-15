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

    // Rute pembuatan didaftarkan SEBELUM `/documents/{document}`. Tanpa urutan
    // ini, "create" akan ditangkap sebagai id dokumen dan berakhir 404.
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::get('/documents/cari-pengguna', [DocumentController::class, 'cariPengguna'])
        ->name('documents.cari-pengguna');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');

    // Ubah, nonaktifkan, dan aktifkan kembali — FEAT-10.
    Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])
        ->name('documents.edit');
    Route::patch('/documents/{document}', [DocumentController::class, 'update'])
        ->name('documents.update');
    // `delete` di sini berarti MENONAKTIFKAN, bukan menghapus baris (FR-10).
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
        ->name('documents.destroy');
    Route::patch('/documents/{document}/restore', [DocumentController::class, 'restore'])
        ->name('documents.restore');

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
