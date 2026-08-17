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
        DB::statement("ALTER TABLE documents MODIFY extraction_status ENUM('not_applicable','pending','completed','review_required','failed') NOT NULL DEFAULT 'not_applicable'");

        Schema::table('documents', function (Blueprint $table): void {
            $table->string('nomor_normalized', 80)->default('')->index()->after('nomor');
            $table->foreignId('replaces_document_id')->nullable()->after('id')->constrained('documents')->nullOnDelete();
        });

        DB::table('documents')->where('extraction_status', 'completed')->whereNull('extracted_text')
            ->update(['extraction_status' => 'review_required', 'extraction_message' => 'Teks tidak terdeteksi pada ekstraksi sebelumnya.']);
        DB::table('documents')->orderBy('id')->each(function (object $document): void {
            DB::table('documents')->where('id', $document->id)->update([
                'nomor_normalized' => preg_replace('/[^a-z0-9]+/i', '', strtolower($document->nomor)) ?? '',
            ]);
        });
    }

    public function down(): void
    {
        DB::table('documents')->where('extraction_status', 'review_required')->update(['extraction_status' => 'failed']);
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign(['replaces_document_id']);
            $table->dropColumn(['replaces_document_id', 'nomor_normalized']);
        });
        DB::statement("ALTER TABLE documents MODIFY extraction_status ENUM('not_applicable','pending','completed','failed') NOT NULL DEFAULT 'not_applicable'");
    }
};
