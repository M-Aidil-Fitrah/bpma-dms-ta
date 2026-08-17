<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('thumbnail_path', 500)->nullable()->after('file_size');
            $table->string('preview_path', 500)->nullable()->after('thumbnail_path');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn(['thumbnail_path', 'preview_path']);
        });
    }
};
