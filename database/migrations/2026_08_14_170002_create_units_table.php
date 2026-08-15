<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unit kerja BPMA — pohon yang merujuk ke dirinya sendiri.
 *
 * Sekretaris dan Deputi berada di tingkat atas (`parent_id` null), divisi
 * berada di bawahnya. Kedalaman pohon tidak dibatasi skema, walau struktur
 * BPMA saat ini hanya dua tingkat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 150)->unique();

            // Unit induk. `nullOnDelete` dipilih, bukan cascade: menghapus unit
            // induk tidak boleh ikut melenyapkan divisi di bawahnya beserta
            // seluruh dokumen yang merujuk padanya.
            $table->foreignId('parent_id')->nullable()
                ->constrained('units')->nullOnDelete();

            // Label bebas: sekretaris / deputi / divisi. Sengaja string, bukan
            // enum — hanya dipakai untuk pengelompokan tampilan, tidak pernah
            // dibaca logika otorisasi.
            $table->string('tipe', 20);

            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
