<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori dokumen. Data dinamis, dikelola Superadmin (FR-14).
 *
 * Satu dokumen memiliki tepat satu kategori — multi-kategori (tag) sengaja
 * tidak diambil untuk prototype (`Catatan_Audit.md` isu #13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 100);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
