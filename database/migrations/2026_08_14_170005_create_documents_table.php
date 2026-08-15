<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entitas utama sistem.
 *
 * Dokumen tidak memiliki satu "tipe visibilitas". Sebagai gantinya ada empat
 * mekanisme akses yang masing-masing dapat diaktifkan secara independen dan
 * berlaku bersamaan — dua di antaranya berupa kolom di tabel ini
 * (`is_shared_to_all`, `min_tingkat_akses`), dua lagi berupa tabel pivot
 * tersendiri (`document_units`, `document_shares`). Dokumen terlihat oleh
 * seseorang bila salah satu saja terpenuhi (`PRD.md` §2.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();

            // --- Identitas ---------------------------------------------------
            $table->string('nomor', 50)->index();
            $table->string('judul', 255)->index();

            $table->foreignId('category_id')
                ->constrained('categories')->restrictOnDelete();

            // Unit asal/pemilik dokumen — dipakai untuk penyaringan dan
            // tampilan (FR-18). INDEPENDEN dari mekanisme akses "unit": mengisi
            // kolom ini tidak memberi akses kepada siapa pun.
            $table->foreignId('origin_unit_id')->nullable()
                ->constrained('units')->restrictOnDelete();

            // --- Masa berlaku ------------------------------------------------
            $table->date('tanggal');

            // Ditentukan pengunggah per dokumen. Saat tanggal ini terlewat,
            // perintah terjadwal harian memindahkan status ke Kadaluarsa
            // (FR-53). Null berarti berlaku tanpa batas waktu.
            $table->date('masa_berlaku')->nullable()->index();

            $table->enum('status', ['berlaku', 'kadaluarsa'])->index();
            $table->text('deskripsi')->nullable();

            // --- Berkas fisik ------------------------------------------------
            // Nama berkas di disk di-UUID-kan dan disimpan di disk `local`;
            // seluruh akses wajib melewati route ter-otorisasi (`PRD.md` §8.2).
            $table->string('file_path', 500);
            $table->string('file_name_original', 255);
            $table->string('file_mime_type', 150);
            $table->unsignedBigInteger('file_size')->default(0);

            // --- Isi yang dapat dicari ---------------------------------------
            // Diisi belakangan oleh job asinkron. Kolom `longText` — DILARANG
            // ikut di-select pada query daftar maupun pencarian.
            $table->longText('extracted_text')->nullable();

            $table->enum('extraction_status', [
                'not_applicable', 'pending', 'completed', 'failed',
            ])->default('not_applicable')->index();

            // --- Mekanisme akses ---------------------------------------------
            // Mekanisme 1: bagikan ke semua pengguna internal.
            $table->boolean('is_shared_to_all')->default(false)->index();

            // Mekanisme 2: bagikan ke jenjang jabatan. Bila terisi, siapa pun
            // dengan tingkat_akses <= nilai ini dapat melihat, lintas unit.
            $table->unsignedTinyInteger('min_tingkat_akses')->nullable();

            // Mekanisme 3 dan 4 berada di tabel `document_units` dan
            // `document_shares`.

            $table->enum('edit_scope', ['owner_only', 'match_visibility'])
                ->default('owner_only');

            // --- Kepemilikan & status baris ----------------------------------
            $table->foreignId('uploaded_by')
                ->constrained('users')->restrictOnDelete();

            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            // Pencarian berbasis isi dokumen (FR-34). Membutuhkan MySQL atau
            // MariaDB — SQLite tidak mendukungnya.
            $table->fullText(['judul', 'deskripsi', 'extracted_text']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
