<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mekanisme akses "bagikan ke unit" (FR-39).
 *
 * Isi tabel ini adalah daftar unit yang benar-benar berhak — bukan daftar yang
 * masih perlu ditafsirkan. Saat pengunggah memilih unit tingkat atas,
 * `DocumentUnitResolver` menuruni pohon dan menyisipkan divisi di bawahnya
 * sebagai baris tersendiri. Cascade diselesaikan sekali di sini, saat
 * menyimpan, bukan dihitung ulang setiap kali membaca — sehingga pengurangan
 * unit secara manual oleh pengunggah benar-benar berlaku
 * (`Catatan_Audit.md` isu #15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_units', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('document_id')
                ->constrained('documents')->cascadeOnDelete();

            // RESTRICT, bukan cascade: unit tidak pernah dihapus permanen lewat
            // aplikasi (hanya dinonaktifkan), dan bila sampai terjadi lewat
            // jalur lain, hak akses dokumen tidak boleh lenyap diam-diam.
            $table->foreignId('unit_id')
                ->constrained('units')->restrictOnDelete();

            // Audit: siapa yang menambahkan unit ini (FR-51b).
            $table->foreignId('added_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_units');
    }
};
