<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DocumentFolder;
use App\Models\Unit;
use App\Models\User;
use App\Services\FolderAccessWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FolderAccessWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_sinkron_menambah_dan_mencabut_akses_sesuai_diminta(): void
    {
        $owner = User::factory()->create();
        $pelaku = User::factory()->create();
        $unit = Unit::factory()->create();
        $lama = User::factory()->create();
        $baru = User::factory()->create();
        $folder = DocumentFolder::create([
            'owner_id' => $owner->id,
            'name' => 'Arsip',
            'name_normalized' => 'arsip',
        ]);
        $folder->sharedUsers()->attach($lama->id, ['granted_by' => $owner->id]);

        $writer = app(FolderAccessWriter::class);
        $perubahan = $writer->sinkron($folder, [$unit->id], [$baru->id], $pelaku);

        $this->assertSame([$baru->id], array_column($perubahan->penggunaDitambahkan, 'id'));
        $this->assertSame([$lama->id], array_column($perubahan->penggunaDicabut, 'id'));
        $this->assertSame([$unit->id], array_column($perubahan->unitDitambahkan, 'id'));
        $this->assertDatabaseHas('document_folder_shares', [
            'folder_id' => $folder->id,
            'user_id' => $baru->id,
            'role' => 'viewer',
        ]);
        $this->assertDatabaseMissing('document_folder_shares', [
            'folder_id' => $folder->id,
            'user_id' => $lama->id,
        ]);
    }

    public function test_sinkron_tidak_menimpa_granted_by_baris_yang_tidak_berubah(): void
    {
        $owner = User::factory()->create();
        $pemberiPertama = User::factory()->create();
        $pelakuKedua = User::factory()->create();
        $tetap = User::factory()->create();
        $folder = DocumentFolder::create([
            'owner_id' => $owner->id,
            'name' => 'Arsip',
            'name_normalized' => 'arsip',
        ]);
        $folder->sharedUsers()->attach($tetap->id, ['granted_by' => $pemberiPertama->id]);

        $writer = app(FolderAccessWriter::class);
        $writer->sinkron($folder, [], [$tetap->id], $pelakuKedua);

        $this->assertDatabaseHas('document_folder_shares', [
            'folder_id' => $folder->id,
            'user_id' => $tetap->id,
            'granted_by' => $pemberiPertama->id,
        ]);
    }

    public function test_menyimpan_role_editor_untuk_pengguna(): void
    {
        $pemilik = User::factory()->create();
        $target = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();

        app(FolderAccessWriter::class)->sinkron($folder, [], [$target->id => 'editor'], $pemilik);

        $this->assertDatabaseHas('document_folder_shares', [
            'folder_id' => $folder->id,
            'user_id' => $target->id,
            'role' => 'editor',
        ]);
    }

    public function test_perubahan_role_viewer_ke_editor_tidak_menulis_ulang_granted_by(): void
    {
        $pemilik = User::factory()->create();
        $editorLain = User::factory()->create();
        $target = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $folder->sharedUsers()->attach($target->id, ['role' => 'viewer', 'granted_by' => $pemilik->id]);

        app(FolderAccessWriter::class)->sinkron($folder, [], [$target->id => 'editor'], $editorLain);

        $this->assertDatabaseHas('document_folder_shares', [
            'folder_id' => $folder->id,
            'user_id' => $target->id,
            'role' => 'editor',
            'granted_by' => $pemilik->id,
        ]);
    }
}
