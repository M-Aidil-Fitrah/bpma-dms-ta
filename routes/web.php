<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\JabatanController;
use App\Http\Controllers\Admin\PengaturanController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentWorkspaceController;
use App\Http\Controllers\LocaleController;
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
| Bahasa — dua bahasa (id/en)
|--------------------------------------------------------------------------
|
| Sengaja di luar middleware `auth`: halaman masuk juga butuh pemilih bahasa.
|
*/

Route::put('/locale', [LocaleController::class, 'update'])->name('locale.update');

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
    Route::get('/documents/mine', [DocumentWorkspaceController::class, 'mine'])->name('documents.mine');
    Route::get('/documents/starred', [DocumentWorkspaceController::class, 'starred'])->name('documents.starred');
    Route::get('/documents/recent', [DocumentWorkspaceController::class, 'recent'])->name('documents.recent');
    Route::get('/trash', [DocumentWorkspaceController::class, 'trash'])->name('documents.trash');

    Route::post('/folders', [DocumentWorkspaceController::class, 'storeFolder'])->middleware('throttle:mutation')->name('folders.store');
    Route::patch('/folders/{folder}', [DocumentWorkspaceController::class, 'updateFolder'])->middleware('throttle:mutation')->name('folders.update');
    Route::delete('/folders/{folder}', [DocumentWorkspaceController::class, 'trashFolder'])->middleware('throttle:mutation')->name('folders.destroy');
    Route::patch('/folders/{folder}/restore', [DocumentWorkspaceController::class, 'restoreFolder'])->middleware('throttle:mutation')->name('folders.restore');
    Route::get('/folders/{folder}', [DocumentWorkspaceController::class, 'folder'])->name('folders.show');

    // Rute pembuatan didaftarkan SEBELUM `/documents/{document}`. Tanpa urutan
    // ini, "create" akan ditangkap sebagai id dokumen dan berakhir 404.
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::get('/documents/cari-pengguna', [DocumentController::class, 'cariPengguna'])
        ->middleware('throttle:search')
        ->name('documents.cari-pengguna');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::put('/documents/{document}/star', [DocumentWorkspaceController::class, 'star'])->middleware('throttle:mutation')->name('documents.star');
    Route::delete('/documents/{document}/star', [DocumentWorkspaceController::class, 'unstar'])->middleware('throttle:mutation')->name('documents.unstar');
    Route::put('/documents/{document}/folder', [DocumentWorkspaceController::class, 'place'])->middleware('throttle:mutation')->name('documents.folder');
    Route::delete('/documents/{document}/folder', [DocumentWorkspaceController::class, 'moveToRoot'])->middleware('throttle:mutation')->name('documents.folder-root');
    Route::get('/documents/{document}/csv-preview', [DocumentController::class, 'previewCsv'])
        ->name('documents.csv-preview');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');

    // Ubah, nonaktifkan, dan aktifkan kembali — FEAT-10.
    Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])
        ->name('documents.edit');
    Route::patch('/documents/{document}', [DocumentController::class, 'update'])
        ->middleware('throttle:mutation')
        ->name('documents.update');
    Route::post('/documents/{document}/restore-version', [DocumentController::class, 'restoreVersion'])
        ->middleware(['password.confirm', 'throttle:mutation'])
        ->name('documents.restore-version');
    // `delete` di sini memindahkan dokumen ke Sampah, bukan menghapus baris.
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
        ->middleware(['password.confirm', 'throttle:mutation'])
        ->name('documents.destroy');
    Route::patch('/documents/{document}/restore-trash', [DocumentController::class, 'restoreTrash'])
        ->middleware(['password.confirm', 'throttle:mutation'])
        ->name('documents.restore-trash');
    Route::patch('/documents/{document}/restore', [DocumentController::class, 'restore'])
        ->middleware(['password.confirm', 'throttle:mutation'])
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
    // Pemantauan lintas pengguna (FEAT-15b) — beda dari `/activity-log`
    // biasa yang dibatasi ke aktivitas yang dapat diakses masing-masing.
    Route::get('/activity-log', [AdminActivityLogController::class, 'index'])->name('activity-log.index');
    Route::get('/activity-log/cari-pengguna', [AdminActivityLogController::class, 'cariPengguna'])
        ->middleware('throttle:search')
        ->name('activity-log.cari-pengguna');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware(['password.confirm', 'throttle:mutation'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('/users/{user}', [UserController::class, 'update'])->middleware(['password.confirm', 'throttle:mutation'])->name('users.update');
    // `delete` di sini berarti MENONAKTIFKAN, sama seperti dokumen (FR-27) —
    // riwayat dan dokumen yang pernah diunggah akun ini tetap utuh.
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware(['password.confirm', 'throttle:mutation'])->name('users.destroy');
    Route::patch('/users/{user}/restore', [UserController::class, 'restore'])->middleware(['password.confirm', 'throttle:mutation'])->name('users.restore');
    Route::patch('/users/{user}/password', [UserController::class, 'resetPassword'])->middleware(['password.confirm', 'throttle:mutation'])->name('users.password');

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
    Route::post('/jabatans', [JabatanController::class, 'store'])->middleware(['password.confirm', 'throttle:mutation'])->name('jabatans.store');
    Route::get('/jabatans/{jabatan}/edit', [JabatanController::class, 'edit'])->name('jabatans.edit');
    Route::patch('/jabatans/{jabatan}', [JabatanController::class, 'update'])->middleware(['password.confirm', 'throttle:mutation'])->name('jabatans.update');
    Route::delete('/jabatans/{jabatan}', [JabatanController::class, 'destroy'])->middleware(['password.confirm', 'throttle:mutation'])->name('jabatans.destroy');
    Route::patch('/jabatans/{jabatan}/restore', [JabatanController::class, 'restore'])->middleware(['password.confirm', 'throttle:mutation'])->name('jabatans.restore');

    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::get('/units/create', [UnitController::class, 'create'])->name('units.create');
    Route::post('/units', [UnitController::class, 'store'])->middleware(['password.confirm', 'throttle:mutation'])->name('units.store');
    Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
    Route::patch('/units/{unit}', [UnitController::class, 'update'])->middleware(['password.confirm', 'throttle:mutation'])->name('units.update');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->middleware(['password.confirm', 'throttle:mutation'])->name('units.destroy');
    Route::patch('/units/{unit}/restore', [UnitController::class, 'restore'])->middleware(['password.confirm', 'throttle:mutation'])->name('units.restore');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware(['password.confirm', 'throttle:mutation'])->name('categories.store');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->middleware(['password.confirm', 'throttle:mutation'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware(['password.confirm', 'throttle:mutation'])->name('categories.destroy');
    Route::patch('/categories/{category}/restore', [CategoryController::class, 'restore'])->middleware(['password.confirm', 'throttle:mutation'])->name('categories.restore');

    Route::get('/settings', [PengaturanController::class, 'index'])->name('settings.index');
    Route::patch('/settings', [PengaturanController::class, 'update'])->middleware(['password.confirm', 'throttle:mutation'])->name('settings.update');
});

/*
|--------------------------------------------------------------------------
| Modul: Profil Pengguna — bawaan Breeze
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('throttle:mutation')->name('profile.update');
    // Tanpa rute hapus akun — lihat catatan di ProfileController.
});

require __DIR__.'/auth.php';
