<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignId('version_root_id')->nullable()->after('replaces_document_id')
                ->constrained('documents')->restrictOnDelete();
            $table->unsignedInteger('version_major')->default(1)->after('version_root_id');
            $table->unsignedInteger('version_minor')->default(0)->after('version_major');
            $table->enum('version_kind', ['content', 'metadata', 'restoration'])->default('content')->after('version_minor');
            $table->string('version_note', 500)->default('Unggahan awal')->after('version_kind');
        });

        /** @var array<int, array{replaces_document_id: int|null}> $dokumen */
        $dokumen = DB::table('documents')->orderBy('id')->pluck('replaces_document_id', 'id')->all();
        $tersisa = $dokumen;
        $versi = [];

        while ($tersisa !== []) {
            $maju = false;

            foreach ($tersisa as $id => $indukId) {
                if ($indukId !== null && ! isset($versi[$indukId])) {
                    continue;
                }

                $induk = $indukId === null ? null : $versi[$indukId];
                $versi[$id] = [
                    'root' => $induk['root'] ?? $id,
                    'major' => ($induk['major'] ?? 0) + 1,
                ];
                unset($tersisa[$id]);
                $maju = true;
            }

            if (! $maju) {
                throw new RuntimeException('Rantai pengganti dokumen lama mengandung siklus atau induk yang hilang.');
            }
        }

        foreach ($versi as $id => $nilai) {
            DB::table('documents')->where('id', $id)->update([
                'version_root_id' => $nilai['root'],
                'version_major' => $nilai['major'],
                'version_minor' => 0,
            ]);
        }

        Schema::table('documents', function (Blueprint $table): void {
            // Keduanya menutup cabang ganda dan nomor versi kembar bahkan bila
            // dua permintaan tiba hampir bersamaan.
            $table->unique('replaces_document_id');
            $table->unique(['version_root_id', 'version_major', 'version_minor'], 'documents_version_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropUnique('documents_version_number_unique');
            $table->dropUnique(['replaces_document_id']);
            $table->dropForeign(['version_root_id']);
            $table->dropColumn([
                'version_root_id',
                'version_major',
                'version_minor',
                'version_kind',
                'version_note',
            ]);
        });
    }
};
