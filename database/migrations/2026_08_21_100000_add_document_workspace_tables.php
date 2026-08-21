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
            $table->timestamp('trashed_at')->nullable()->after('is_active')->index();
            $table->foreignId('trashed_by')->nullable()->after('trashed_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('purge_after')->nullable()->after('trashed_by')->index();
            $table->uuid('trash_token')->nullable()->after('purge_after')->index();
        });

        Schema::create('document_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('document_folders')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('name_normalized', 120);
            $table->timestamp('trashed_at')->nullable()->index();
            $table->foreignId('trashed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('purge_after')->nullable()->index();
            $table->uuid('trash_token')->nullable()->index();
            $table->timestamps();

            $table->index(['owner_id', 'parent_id']);
            $table->index(['owner_id', 'parent_id', 'name_normalized']);
        });

        Schema::create('document_placements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('folder_id')->constrained('document_folders')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['owner_id', 'document_id']);
            $table->unique(['folder_id', 'document_id']);
        });

        Schema::create('document_stars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'document_id']);
        });

        Schema::create('document_recents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->timestamp('last_opened_at')->index();
            $table->timestamps();

            $table->unique(['user_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_recents');
        Schema::dropIfExists('document_stars');
        Schema::dropIfExists('document_placements');
        Schema::dropIfExists('document_folders');

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign(['trashed_by']);
            $table->dropIndex(['trashed_at']);
            $table->dropIndex(['purge_after']);
            $table->dropIndex(['trash_token']);
            $table->dropColumn(['trashed_at', 'trashed_by', 'purge_after', 'trash_token']);
        });
    }
};
