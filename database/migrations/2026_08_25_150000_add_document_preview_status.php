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
            $table->string('preview_status', 32)->default('not_applicable')->after('preview_path')->index();
            $table->string('preview_message', 500)->nullable()->after('preview_status');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex(['preview_status']);
            $table->dropColumn(['preview_status', 'preview_message']);
        });
    }
};
