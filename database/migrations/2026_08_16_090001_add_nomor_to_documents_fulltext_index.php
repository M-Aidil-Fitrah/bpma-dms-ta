<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyatukan `nomor` ke index FULLTEXT yang sama dengan judul/deskripsi/isi
 * (FEAT-12).
 *
 * Bukan kosmetik: MariaDB/MySQL berhenti memakai index FULLTEXT sama sekali
 * begitu `MATCH...AGAINST` digabung `OR` dengan kondisi `LIKE` pada kolom
 * lain — dibuktikan lewat `EXPLAIN` (`type` jatuh ke `ALL`, pemindaian tabel
 * penuh). Menyatukan `nomor` ke DALAM index yang sama menghindari kombinasi
 * itu; pencarian nomor dokumen tetap bekerja karena parser FULLTEXT memecah
 * "001/BPMA/X/I/2026" menjadi token per bagian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropFullText(['judul', 'deskripsi', 'extracted_text']);
            $table->fullText(['nomor', 'judul', 'deskripsi', 'extracted_text']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropFullText(['nomor', 'judul', 'deskripsi', 'extracted_text']);
            $table->fullText(['judul', 'deskripsi', 'extracted_text']);
        });
    }
};
