<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mekanisme "bagikan folder ke unit" — padanan `document_units` untuk folder
 * Dokumen Saya. `restrictOnDelete()` pada `unit_id` sama alasannya dengan
 * `document_units`: unit tidak pernah dihapus permanen lewat aplikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_folder_units', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('folder_id')
                ->constrained('document_folders')->cascadeOnDelete();

            $table->foreignId('unit_id')
                ->constrained('units')->restrictOnDelete();

            $table->string('role', 20)->default('viewer');

            $table->foreignId('added_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['folder_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_folder_units');
    }
};
