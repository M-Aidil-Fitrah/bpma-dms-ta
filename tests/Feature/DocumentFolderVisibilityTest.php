<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DocumentFolder;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DocumentFolderVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pemilik_selalu_bisa_melihat_foldernya(): void
    {
        $owner = User::factory()->create();
        $folder = DocumentFolder::create([
            'owner_id' => $owner->id,
            'name' => 'Arsip',
            'name_normalized' => 'arsip',
        ]);

        $this->assertTrue($folder->terlihatOleh($owner));
    }

    public function test_user_tak_terkait_tidak_bisa_melihat_folder(): void
    {
        $owner = User::factory()->create();
        $lain = User::factory()->create();
        $folder = DocumentFolder::create([
            'owner_id' => $owner->id,
            'name' => 'Arsip',
            'name_normalized' => 'arsip',
        ]);

        $this->assertFalse($folder->terlihatOleh($lain));
    }

    public function test_folder_yang_dibagikan_langsung_terlihat_penerima(): void
    {
        $owner = User::factory()->create();
        $penerima = User::factory()->create();
        $folder = DocumentFolder::create([
            'owner_id' => $owner->id,
            'name' => 'Arsip',
            'name_normalized' => 'arsip',
        ]);
        $folder->sharedUsers()->attach($penerima->id, ['granted_by' => $owner->id]);

        $this->assertTrue($folder->terlihatOleh($penerima));
    }

    public function test_subfolder_terlihat_lewat_share_pada_leluhurnya(): void
    {
        $owner = User::factory()->create();
        $penerima = User::factory()->create();
        $induk = DocumentFolder::create([
            'owner_id' => $owner->id,
            'name' => 'Induk',
            'name_normalized' => 'induk',
        ]);
        $anak = DocumentFolder::create([
            'owner_id' => $owner->id,
            'parent_id' => $induk->id,
            'name' => 'Anak',
            'name_normalized' => 'anak',
        ]);
        $induk->sharedUsers()->attach($penerima->id, ['granted_by' => $owner->id]);

        $this->assertTrue($anak->fresh()->terlihatOleh($penerima));
    }

    public function test_folder_terlihat_lewat_share_ke_unit_pengguna(): void
    {
        $owner = User::factory()->create();
        $unit = Unit::factory()->create();
        $penerima = User::factory()->create(['unit_id' => $unit->id]);
        $folder = DocumentFolder::create([
            'owner_id' => $owner->id,
            'name' => 'Arsip',
            'name_normalized' => 'arsip',
        ]);
        $folder->targetUnits()->attach($unit->id, ['added_by' => $owner->id]);

        $this->assertTrue($folder->terlihatOleh($penerima));
    }

    public function test_dibagikan_sebagai_editor_langsung(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $folder->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);

        $this->assertTrue($folder->terlihatSebagaiEditorOleh($editor));
        $this->assertTrue($folder->dibagikanSebagaiEditorKe($editor));
    }

    public function test_role_viewer_bukan_editor(): void
    {
        $pemilik = User::factory()->create();
        $viewer = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $folder->sharedUsers()->attach($viewer->id, ['role' => 'viewer', 'granted_by' => $pemilik->id]);

        $this->assertFalse($folder->terlihatSebagaiEditorOleh($viewer));
        $this->assertTrue($folder->terlihatOleh($viewer)); // regresi Fase 1: viewer tetap bisa melihat
    }

    public function test_editor_pada_folder_induk_menurun_ke_subfolder(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $induk = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $anak = DocumentFolder::factory()->for($pemilik, 'owner')->create(['parent_id' => $induk->id]);
        $induk->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);

        $this->assertTrue($anak->terlihatSebagaiEditorOleh($editor));
        $this->assertFalse($anak->dibagikanSebagaiEditorKe($editor)); // grant langsung ada di induk, bukan di anak
    }

    public function test_editor_via_unit(): void
    {
        $unit = Unit::factory()->create();
        $pemilik = User::factory()->create();
        $editor = User::factory()->create(['unit_id' => $unit->id]);
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $folder->targetUnits()->attach($unit->id, ['role' => 'editor', 'added_by' => $pemilik->id]);

        $this->assertTrue($folder->terlihatSebagaiEditorOleh($editor));
    }

    public function test_pemilik_selalu_editor_atas_foldernya(): void
    {
        $pemilik = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();

        $this->assertTrue($folder->terlihatSebagaiEditorOleh($pemilik));
    }
}
