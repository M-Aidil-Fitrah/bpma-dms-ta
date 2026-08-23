<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Nullable: `null` berarti "ikuti bahasa peramban/cookie", bukan
            // paksaan eksplisit. Dibedakan dari default 'id' supaya pengguna
            // yang belum pernah memilih bahasa tidak terkunci ke Indonesia
            // begitu suatu saat bahasa bawaan aplikasi berubah.
            $table->string('locale', 5)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
