<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class FolderShareSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabel_document_folder_shares_punya_kolom_yang_benar(): void
    {
        $this->assertTrue(Schema::hasTable('document_folder_shares'));
        $this->assertTrue(Schema::hasColumns('document_folder_shares', [
            'id', 'folder_id', 'user_id', 'role', 'granted_by', 'created_at',
        ]));
    }

    public function test_tabel_document_folder_units_punya_kolom_yang_benar(): void
    {
        $this->assertTrue(Schema::hasTable('document_folder_units'));
        $this->assertTrue(Schema::hasColumns('document_folder_units', [
            'id', 'folder_id', 'unit_id', 'role', 'added_by', 'created_at',
        ]));
    }
}
