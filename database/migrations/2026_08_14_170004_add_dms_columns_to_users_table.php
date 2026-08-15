<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan atribut organisasi ke tabel `users`.
 *
 * Dibuat sebagai migration terpisah, bukan dengan menyunting migration bawaan
 * Laravel. Berkas bawaan bernama `0001_01_01_000000_create_users_table.php` dan
 * selalu berjalan paling awal — menambahkan foreign key ke `jabatans` di sana
 * pasti gagal karena tabelnya belum terbentuk (`Catatan_Audit.md` isu #17).
 *
 * Jabatan dan unit adalah *atribut* pengguna, bukan role. Role sistem tetap dua
 * (`superadmin`, `pengguna`); cakupan akses seseorang ditentukan kombinasi
 * atribut ini dengan mekanisme akses tiap dokumen (`PRD.md` §2.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Null hanya untuk Superadmin, yang berada di luar struktur
            // organisasi. Seluruh akun lain wajib berjabatan.
            $table->foreignId('jabatan_id')->nullable()->after('email')
                ->constrained('jabatans')->restrictOnDelete();

            // Null untuk Superadmin dan jabatan tingkat 1 (Kepala/Wakil Kepala
            // BPMA), yang cakupannya melintasi seluruh unit.
            $table->foreignId('unit_id')->nullable()->after('jabatan_id')
                ->constrained('units')->restrictOnDelete();

            // Menonaktifkan akun, bukan menghapusnya — riwayat aktivitas dan
            // dokumen yang pernah diunggahnya tetap utuh (FR-27).
            $table->boolean('is_active')->default(true)->after('unit_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['jabatan_id']);
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['jabatan_id', 'unit_id', 'is_active']);
        });
    }
};
