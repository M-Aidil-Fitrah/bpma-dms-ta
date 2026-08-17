<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\JabatanController;
use App\Http\Controllers\Admin\PengaturanController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
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
    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
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
    Route::get('/documents/{document}/thumbnail', [DocumentController::class, 'thumbnail'])
        ->name('documents.thumbnail');
});

/*
|--------------------------------------------------------------------------
| Modul: Manajemen Pengguna — FEAT-13
|--------------------------------------------------------------------------
|
| Tidak ada registrasi publik — ini satu-satunya jalan sebuah akun `pengguna`
| bisa terbentuk. Middleware `superadmin` menjaga SELURUH grup, bukan hanya
| menyembunyikan tautannya di antarmuka (FR-43).
*/

Route::middleware(['auth', 'superadmin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    // `delete` di sini berarti MENONAKTIFKAN, sama seperti dokumen (FR-27) —
    // riwayat dan dokumen yang pernah diunggah akun ini tetap utuh.
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::patch('/users/{user}/restore', [UserController::class, 'restore'])->name('users.restore');
    Route::patch('/users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');

    /*
    |--------------------------------------------------------------------------
    | Modul: Manajemen Organisasi & Pengaturan — FEAT-14
    |--------------------------------------------------------------------------
    |
    | Ketiga resource memakai aksi `destroy` sebagai soft-disable. Rute
    | restore terpisah supaya tindakan mengaktifkan kembali tetap eksplisit.
    |
    */
    Route::get('/jabatans', [JabatanController::class, 'index'])->name('jabatans.index');
    Route::get('/jabatans/create', [JabatanController::class, 'create'])->name('jabatans.create');
    Route::post('/jabatans', [JabatanController::class, 'store'])->name('jabatans.store');
    Route::get('/jabatans/{jabatan}/edit', [JabatanController::class, 'edit'])->name('jabatans.edit');
    Route::patch('/jabatans/{jabatan}', [JabatanController::class, 'update'])->name('jabatans.update');
    Route::delete('/jabatans/{jabatan}', [JabatanController::class, 'destroy'])->name('jabatans.destroy');
    Route::patch('/jabatans/{jabatan}/restore', [JabatanController::class, 'restore'])->name('jabatans.restore');

    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::get('/units/create', [UnitController::class, 'create'])->name('units.create');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store');
    Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
    Route::patch('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    Route::patch('/units/{unit}/restore', [UnitController::class, 'restore'])->name('units.restore');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::patch('/categories/{category}/restore', [CategoryController::class, 'restore'])->name('categories.restore');

    Route::get('/settings', [PengaturanController::class, 'index'])->name('settings.index');
    Route::patch('/settings', [PengaturanController::class, 'update'])->name('settings.update');
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
