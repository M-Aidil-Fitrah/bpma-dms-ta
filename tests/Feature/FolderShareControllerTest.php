<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentPlacement;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class FolderShareControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $penerima;

    private DocumentFolder $folder;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(User::ROLE_PENGGUNA, 'web');
        $unit = Unit::factory()->create();
        $jabatan = Jabatan::factory()->create(['tingkat_akses' => 4]);
        $this->owner = User::factory()->create(['unit_id' => $unit->id, 'jabatan_id' => $jabatan->id]);
        $this->owner->assignRole(User::ROLE_PENGGUNA);
        $this->penerima = User::factory()->create(['unit_id' => $unit->id, 'jabatan_id' => $jabatan->id]);
        $this->penerima->assignRole(User::ROLE_PENGGUNA);
        $this->folder = DocumentFolder::create([
            'owner_id' => $this->owner->id,
            'name' => 'Arsip',
            'name_normalized' => 'arsip',
        ]);
    }

    public function test_non_pemilik_tidak_boleh_membagikan_folder(): void
    {
        $this->actingAs($this->penerima)
            ->putJson(route('folders.share', $this->folder), ['shared_user_ids' => [$this->penerima->id]])
            ->assertForbidden();
    }

    public function test_pemilik_membagikan_folder_tercatat_di_aktivitas(): void
    {
        $this->actingAs($this->owner)
            ->put(route('folders.share', $this->folder), ['shared_user_ids' => [$this->penerima->id]])
            ->assertRedirect();

        $this->assertDatabaseHas('document_folder_shares', [
            'folder_id' => $this->folder->id,
            'user_id' => $this->penerima->id,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => ActivityLogName::FolderShare->value,
            'event' => AuditEvent::AccessGranted->value,
            'subject_id' => $this->folder->id,
            'causer_id' => $this->owner->id,
        ]);
    }

    public function test_penerima_share_melihat_folder_di_halaman_dibagikan_ke_saya(): void
    {
        $this->folder->sharedUsers()->attach($this->penerima->id, ['granted_by' => $this->owner->id]);

        $this->actingAs($this->penerima)
            ->get(route('documents.shared'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Workspace/Shared')
                ->where('folders.0.id', $this->folder->id));
    }

    public function test_penerima_share_bisa_membuka_folder_dan_melihat_dokumen_pemilik(): void
    {
        $dokumen = Document::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'uploaded_by' => $this->owner->id,
        ]);
        DocumentPlacement::create([
            'owner_id' => $this->owner->id,
            'document_id' => $dokumen->id,
            'folder_id' => $this->folder->id,
        ]);
        $this->folder->sharedUsers()->attach($this->penerima->id, ['granted_by' => $this->owner->id]);

        $this->actingAs($this->penerima)
            ->get(route('folders.show', $this->folder))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Workspace/Index')
                ->where('folder.owner_id', $this->owner->id)
                ->where('folder_options', [])
                ->where('dokumen.data.0.id', $dokumen->id)
                ->where('breadcrumbs.0.label', 'Dibagikan ke saya'));
    }

    public function test_penerima_share_tidak_melihat_dokumen_pribadi_pemilik(): void
    {
        $kategori = Category::factory()->create();
        $publik = Document::factory()->create(['category_id' => $kategori->id, 'uploaded_by' => $this->owner->id]);
        $pribadi = Document::factory()->create([
            'category_id' => $kategori->id,
            'uploaded_by' => $this->owner->id,
            'is_private' => true,
        ]);

        foreach ([$publik, $pribadi] as $dokumen) {
            DocumentPlacement::create([
                'owner_id' => $this->owner->id,
                'document_id' => $dokumen->id,
                'folder_id' => $this->folder->id,
            ]);
        }

        $this->folder->sharedUsers()->attach($this->penerima->id, ['granted_by' => $this->owner->id]);

        // Membagikan folder tidak membatalkan keputusan "Hanya saya" yang
        // dipasang pemiliknya pada dokumen di dalamnya — judul dan metadatanya
        // pun tidak boleh ikut terdaftar.
        $this->actingAs($this->penerima)
            ->get(route('folders.show', $this->folder))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('dokumen.data', 1)
                ->where('dokumen.data.0.id', $publik->id));

        // Pemiliknya sendiri tetap melihat keduanya.
        $this->actingAs($this->owner)
            ->get(route('folders.show', $this->folder))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('dokumen.data', 2));
    }

    public function test_breadcrumb_penerima_share_berhenti_di_folder_yang_dibagikan(): void
    {
        // Folder yang dibagikan sengaja punya induk yang TIDAK dibagikan:
        // tanpa itu, jejak berhenti dengan sendirinya di akar dan aturan
        // "berhenti di batas share" tidak benar-benar teruji.
        $induk = DocumentFolder::create([
            'owner_id' => $this->owner->id,
            'name' => 'Berkas Pribadi Pemilik',
            'name_normalized' => 'berkas pribadi pemilik',
        ]);
        $this->folder->update(['parent_id' => $induk->id]);

        $sub = DocumentFolder::create([
            'owner_id' => $this->owner->id,
            'parent_id' => $this->folder->id,
            'name' => 'Rapat',
            'name_normalized' => 'rapat',
        ]);
        $subSub = DocumentFolder::create([
            'owner_id' => $this->owner->id,
            'parent_id' => $sub->id,
            'name' => 'Notulen',
            'name_normalized' => 'notulen',
        ]);
        $this->folder->sharedUsers()->attach($this->penerima->id, ['granted_by' => $this->owner->id]);

        // Folder yang dibagikan adalah akar jejak: apa pun di atasnya milik
        // pemilik dan tidak pernah diberikan kepada penerima, jadi tidak boleh
        // muncul sebagai tautan.
        $this->actingAs($this->penerima)
            ->get(route('folders.show', $subSub))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('breadcrumbs', [
                ['label' => 'Dibagikan ke saya', 'href' => route('documents.shared')],
                ['label' => 'Arsip', 'href' => route('folders.show', $this->folder)],
                ['label' => 'Rapat', 'href' => route('folders.show', $sub)],
                ['label' => 'Notulen', 'href' => route('folders.show', $subSub)],
            ]));
    }

    public function test_penerima_share_tidak_menerima_daftar_akses_subfolder(): void
    {
        $subfolder = DocumentFolder::create([
            'owner_id' => $this->owner->id,
            'parent_id' => $this->folder->id,
            'name' => 'Notulen',
            'name_normalized' => 'notulen',
        ]);
        $unitLain = Unit::factory()->create();
        $orangLain = User::factory()->create(['unit_id' => $unitLain->id]);
        $orangLain->assignRole(User::ROLE_PENGGUNA);
        $subfolder->targetUnits()->attach($unitLain->id, ['added_by' => $this->owner->id]);
        $subfolder->sharedUsers()->attach($orangLain->id, ['granted_by' => $this->owner->id]);
        $this->folder->sharedUsers()->attach($this->penerima->id, ['granted_by' => $this->owner->id]);

        // Penerima share memang boleh melihat subfoldernya, tetapi tidak boleh
        // tahu siapa saja yang juga diberi akses ke subfolder itu.
        $this->actingAs($this->penerima)
            ->get(route('folders.show', $this->folder))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Workspace/Index')
                ->where('folders.0.id', $subfolder->id)
                ->where('folders.0.unit_ids', [])
                ->where('folders.0.shared_users', []));

        // Sebaliknya, pemilik tetap menerimanya — itu isi awal dialog Bagikan.
        $this->actingAs($this->owner)
            ->get(route('folders.show', $this->folder))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Workspace/Index')
                ->where('folders.0.unit_ids', [$unitLain->id])
                ->where('folders.0.shared_users.0.id', $orangLain->id));
    }

    /**
     * `view` melebar menjadi "pemilik ATAU penerima share" saat folder mulai
     * dibagikan, sehingga memulihkan folder harus dijaga `update` yang tetap
     * owner-only. Kalau penjagaannya kembali ke `view`, penerima lolos policy
     * dan hanya tertahan lapisan service — 422, bukan 403.
     */
    public function test_penerima_share_tidak_bisa_memulihkan_folder_dari_sampah(): void
    {
        $this->folder->sharedUsers()->attach($this->penerima->id, ['granted_by' => $this->owner->id]);
        app(DocumentWorkspaceService::class)->trashFolder($this->folder, $this->owner);

        $this->actingAs($this->penerima)
            ->patchJson(route('folders.restore', $this->folder))
            ->assertForbidden();

        $this->assertNotNull($this->folder->fresh()->trashed_at);
    }

    public function test_user_tak_terkait_tidak_bisa_membuka_folder(): void
    {
        $lain = User::factory()->create(['unit_id' => Unit::factory()->create()->id]);
        $lain->assignRole(User::ROLE_PENGGUNA);

        $this->actingAs($lain)
            ->get(route('folders.show', $this->folder))
            ->assertForbidden();
    }

    public function test_editor_membuat_subfolder_lewat_endpoint(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $induk = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $induk->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);

        $this->actingAs($editor)
            ->post('/folders', ['name' => 'Sub editor', 'parent_id' => $induk->id])
            ->assertRedirect();

        $this->assertDatabaseHas('document_folders', [
            'parent_id' => $induk->id,
            'name' => 'Sub editor',
            'owner_id' => $pemilik->id,
        ]);
    }

    public function test_viewer_ditolak_403_membuat_subfolder(): void
    {
        $pemilik = User::factory()->create();
        $viewer = User::factory()->create();
        $induk = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $induk->sharedUsers()->attach($viewer->id, ['role' => 'viewer', 'granted_by' => $pemilik->id]);

        $this->actingAs($viewer)
            ->post('/folders', ['name' => 'X', 'parent_id' => $induk->id])
            ->assertForbidden();
    }

    public function test_editor_boleh_rename_folder_lewat_endpoint(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create(['name' => 'Lama']);
        $folder->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);

        $this->actingAs($editor)
            ->patch("/folders/{$folder->id}", ['name' => 'Baru'])
            ->assertRedirect();

        $this->assertDatabaseHas('document_folders', ['id' => $folder->id, 'name' => 'Baru']);
    }

    public function test_viewer_ditolak_403_rename_folder(): void
    {
        $pemilik = User::factory()->create();
        $viewer = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create(['name' => 'Lama']);
        $folder->sharedUsers()->attach($viewer->id, ['role' => 'viewer', 'granted_by' => $pemilik->id]);

        $this->actingAs($viewer)
            ->patch("/folders/{$folder->id}", ['name' => 'Baru'])
            ->assertForbidden();

        $this->assertDatabaseHas('document_folders', ['id' => $folder->id, 'name' => 'Lama']);
    }

    public function test_editor_ditolak_403_men_trash_folder(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $folder->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);

        $this->actingAs($editor)->delete("/folders/{$folder->id}")->assertForbidden();
    }

    public function test_editor_place_dan_move_to_root_dokumennya(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $folder->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);
        $doc = Document::factory()->create(['uploaded_by' => $editor->id]);

        $this->actingAs($editor)
            ->put("/documents/{$doc->id}/folder", ['folder_id' => $folder->id])
            ->assertRedirect();

        $this->assertDatabaseHas('document_placements', [
            'owner_id' => $pemilik->id,
            'document_id' => $doc->id,
            'folder_id' => $folder->id,
        ]);

        $this->actingAs($editor)
            ->delete("/documents/{$doc->id}/folder", ['folder_id' => $folder->id])
            ->assertRedirect();

        $this->assertDatabaseMissing('document_placements', [
            'owner_id' => $pemilik->id,
            'document_id' => $doc->id,
        ]);
    }
}
