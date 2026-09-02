<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DocumentFolder;
use App\Models\User;
use App\Policies\DocumentFolderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DocumentFolderPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_penerima_share_boleh_view_tapi_tidak_boleh_update_atau_share(): void
    {
        $owner = User::factory()->create();
        $penerima = User::factory()->create();
        $folder = DocumentFolder::create([
            'owner_id' => $owner->id,
            'name' => 'Arsip',
            'name_normalized' => 'arsip',
        ]);
        $folder->sharedUsers()->attach($penerima->id, ['granted_by' => $owner->id]);

        $this->assertTrue($penerima->can('view', $folder));
        $this->assertFalse($penerima->can('update', $folder));
        $this->assertFalse($penerima->can('delete', $folder));
        $this->assertFalse($penerima->can('share', $folder));
    }

    public function test_pemilik_boleh_share_penerima_tidak(): void
    {
        $owner = User::factory()->create();
        $folder = DocumentFolder::create([
            'owner_id' => $owner->id,
            'name' => 'Arsip',
            'name_normalized' => 'arsip',
        ]);

        $this->assertTrue($owner->can('share', $folder));
    }

    public function test_editor_boleh_edit_tapi_tidak_update_delete_restrict(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $folder->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);
        $policy = new DocumentFolderPolicy();

        $this->assertTrue($policy->edit($editor, $folder));
        $this->assertFalse($policy->update($editor, $folder));   // rename/restore folder tetap owner-only (R2)
        $this->assertFalse($policy->delete($editor, $folder));   // trash folder tetap owner-only
        $this->assertFalse($policy->restrictSharing($editor, $folder));
    }

    public function test_viewer_tidak_boleh_edit_update_delete(): void
    {
        $pemilik = User::factory()->create();
        $viewer = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $folder->sharedUsers()->attach($viewer->id, ['role' => 'viewer', 'granted_by' => $pemilik->id]);
        $policy = new DocumentFolderPolicy();

        $this->assertFalse($policy->edit($viewer, $folder));
        $this->assertFalse($policy->update($viewer, $folder));
    }

    public function test_editor_boleh_share_kecuali_dikunci(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $folder->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);
        $policy = new DocumentFolderPolicy();

        $this->assertTrue($policy->share($editor, $folder));

        $folder->update(['sharing_restricted' => true]);
        $this->assertFalse($policy->share($editor, $folder->fresh()));
        $this->assertTrue($policy->share($pemilik, $folder->fresh())); // pemilik tetap boleh
    }

    public function test_pemilik_boleh_semua(): void
    {
        $pemilik = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $policy = new DocumentFolderPolicy();

        $this->assertTrue($policy->edit($pemilik, $folder));
        $this->assertTrue($policy->update($pemilik, $folder));
        $this->assertTrue($policy->delete($pemilik, $folder));
        $this->assertTrue($policy->share($pemilik, $folder));
        $this->assertTrue($policy->restrictSharing($pemilik, $folder));
    }
}
