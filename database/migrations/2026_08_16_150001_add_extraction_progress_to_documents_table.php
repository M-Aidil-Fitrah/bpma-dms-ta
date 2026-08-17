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
            $table->unsignedSmallInteger('extraction_pages_total')->nullable()->after('extraction_status');
            $table->unsignedSmallInteger('extraction_pages_processed')->nullable()->after('extraction_pages_total');
            $table->unsignedInteger('extraction_estimated_seconds')->nullable()->after('extraction_pages_processed');
            $table->string('extraction_message')->nullable()->after('extraction_estimated_seconds');
            $table->timestamp('extraction_started_at')->nullable()->after('extraction_message');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn([
                'extraction_pages_total',
                'extraction_pages_processed',
                'extraction_estimated_seconds',
                'extraction_message',
                'extraction_started_at',
            ]);
        });
    }
};
