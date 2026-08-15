<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan aplikasi yang dapat diubah Superadmin lewat antarmuka.
 *
 * Berbentuk pasangan kunci–nilai, bukan satu kolom per setelan. Alasannya:
 * setelan baru akan terus bertambah seiring proyek berjalan, dan menambah kolom
 * setiap kali berarti satu migration untuk satu baris konfigurasi.
 *
 * Nilai bawaannya tetap hidup di `config/dms.php`. Tabel ini hanya menyimpan
 * setelan yang benar-benar DIUBAH — kunci yang tidak ada di sini berarti masih
 * memakai bawaan. Dengan begitu, mengubah bawaan di kode tetap berpengaruh pada
 * pemasangan yang belum pernah menyentuh setelan tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table): void {
            $table->id();
            $table->string('kunci', 100)->unique();
            $table->text('nilai')->nullable();
            $table->foreignId('diubah_oleh')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
    }
};
