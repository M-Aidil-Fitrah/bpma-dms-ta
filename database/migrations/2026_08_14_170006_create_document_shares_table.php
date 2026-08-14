<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mekanisme akses "bagikan ke orang tertentu" (FR-41).
 *
 * Daftar eksplisit pengguna yang diberi akses ke satu dokumen, lintas unit dan
 * lintas jabatan. Berdiri sendiri dari ketiga mekanisme lain — boleh terisi
 * bersamaan dengan mereka, boleh juga kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_shares', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('document_id')
                ->constrained('documents')->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            // Audit: siapa yang memberikan akses ini (FR-51). Nullable supaya
            // catatan pemberian akses tidak ikut hilang bila akun pemberinya
            // kelak dihapus.
            $table->foreignId('granted_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_shares');
    }
};
