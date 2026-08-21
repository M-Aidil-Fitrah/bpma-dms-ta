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
            // Berbeda dari dokumen tanpa target akses (yang merupakan data
            // tidak valid), dokumen pribadi adalah keputusan eksplisit.
            $table->boolean('is_private')->default(false)->after('is_shared_to_all')->index();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex(['is_private']);
            $table->dropColumn('is_private');
        });
    }
};
