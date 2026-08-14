<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenjang jabatan dalam struktur organisasi BPMA.
 *
 * Data dinamis: Superadmin dapat menambah dan mengubahnya lewat antarmuka tanpa
 * deploy ulang (FR-29). "Hapus" berarti menonaktifkan lewat kolom `is_active`,
 * tidak pernah hapus permanen, supaya dokumen lama tidak kehilangan rujukan
 * (`Catatan_Audit.md` isu #6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jabatans', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 100)->unique();

            // 1 = tertinggi (Kepala BPMA). Semakin besar angkanya, semakin
            // rendah jenjangnya. Dipakai mekanisme akses "jenjang jabatan":
            // dokumen terlihat bila tingkat_akses pengguna <= min_tingkat_akses.
            $table->unsignedTinyInteger('tingkat_akses');

            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jabatans');
    }
};
