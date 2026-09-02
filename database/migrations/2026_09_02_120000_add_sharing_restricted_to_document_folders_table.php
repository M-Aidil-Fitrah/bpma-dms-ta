<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sharing_restricted` — bila true, hanya pemilik folder yang boleh mengubah
 * daftar akses (Fase 2, keputusan §3.6 versi Google Drive: editor boleh
 * membagikan ulang KECUALI dikunci). Default false = perilaku GD bawaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_folders', function (Blueprint $table): void {
            $table->boolean('sharing_restricted')->default(false)->after('name_normalized');
        });
    }

    public function down(): void
    {
        Schema::table('document_folders', function (Blueprint $table): void {
            $table->dropColumn('sharing_restricted');
        });
    }
};
