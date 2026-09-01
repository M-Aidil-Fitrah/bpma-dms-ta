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
                // `shouldExist: false` — berkas halaman `Workspace/Shared.tsx`
                // baru dibuat di tahap frontend; yang diuji di sini adalah
                // sisi server: nama komponen dan datanya.
                ->component('Workspace/Shared', false)
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

    public function test_user_tak_terkait_tidak_bisa_membuka_folder(): void
    {
        $lain = User::factory()->create(['unit_id' => Unit::factory()->create()->id]);
        $lain->assignRole(User::ROLE_PENGGUNA);

        $this->actingAs($lain)
            ->get(route('folders.show', $this->folder))
            ->assertForbidden();
    }
}
