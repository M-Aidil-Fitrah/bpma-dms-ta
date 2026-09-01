<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mekanisme "bagikan folder ke orang tertentu" — padanan `document_shares`
 * untuk folder Dokumen Saya, mencakup subfolder dan dokumen di dalamnya.
 *
 * `role` disiapkan untuk peran Editor (Fase 2) tapi tidak dipakai Fase 1 —
 * nilainya selalu default `'viewer'`, tidak ada kode yang menuliskannya
 * secara eksplisit sampai Fase 2 membuka opsi itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_folder_shares', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('folder_id')
                ->constrained('document_folders')->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->string('role', 20)->default('viewer');

            $table->foreignId('granted_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['folder_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_folder_shares');
    }
};
