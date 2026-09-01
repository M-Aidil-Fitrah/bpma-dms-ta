<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DocumentFolder;
use App\Models\User;
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
}
