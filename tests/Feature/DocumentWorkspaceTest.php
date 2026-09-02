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
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class DocumentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(User::ROLE_PENGGUNA, 'web');
        $unit = Unit::factory()->create();
        $jabatan = Jabatan::factory()->create(['tingkat_akses' => 4]);
        $this->owner = User::factory()->create(['unit_id' => $unit->id, 'jabatan_id' => $jabatan->id]);
        $this->owner->assignRole(User::ROLE_PENGGUNA);
        $this->other = User::factory()->create(['unit_id' => $unit->id, 'jabatan_id' => $jabatan->id]);
        $this->other->assignRole(User::ROLE_PENGGUNA);
        $this->document = Document::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'origin_unit_id' => $unit->id,
            'uploaded_by' => $this->owner->id,
            'is_shared_to_all' => true,
        ]);
    }

    public function test_pemilik_mengelompokkan_dokumen_miliknya_ke_folder_pribadi(): void
    {
        $this->actingAs($this->owner)->post(route('folders.store'), ['name' => 'Laporan 2026'])->assertRedirect();
        $folder = DocumentFolder::firstWhere('name', 'Laporan 2026');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => ActivityLogName::DocumentWorkspace->value,
            'event' => AuditEvent::FolderCreated->value,
            'subject_id' => $folder->id,
            'causer_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->put(route('documents.folder', $this->document), ['folder_id' => $folder->id])
            ->assertRedirect();

        $this->assertDatabaseHas('document_placements', [
            'owner_id' => $this->owner->id,
            'document_id' => $this->document->id,
            'folder_id' => $folder->id,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => ActivityLogName::DocumentWorkspace->value,
            'event' => AuditEvent::DocumentMoved->value,
            'subject_id' => $this->document->id,
            'causer_id' => $this->owner->id,
        ]);
        $this->assertSame(
            'Laporan 2026',
            Activity::query()
                ->where('event', AuditEvent::DocumentMoved->value)
                ->sole()
                ->getProperty('lokasi_tujuan'),
        );
    }

    public function test_pengguna_lain_tidak_dapat_melihat_folder_atau_memindahkan_dokumen_milik_pengunggah(): void
    {
        $folder = DocumentFolder::create(['owner_id' => $this->owner->id, 'name' => 'Rahasia', 'name_normalized' => 'rahasia']);

        $this->actingAs($this->other)->get(route('folders.show', $folder))->assertForbidden();
        $this->actingAs($this->other)
            ->put(route('documents.folder', $this->document), ['folder_id' => $folder->id])
            ->assertSessionHasErrors('folder');
    }

    public function test_non_pemilik_tidak_dapat_mengubah_atau_membuang_folder(): void
    {
        $folder = DocumentFolder::create(['owner_id' => $this->owner->id, 'name' => 'Rahasia', 'name_normalized' => 'rahasia']);

        $this->actingAs($this->other)
            ->patch(route('folders.update', $folder), ['name' => 'Diubah'])
            ->assertForbidden();
        $this->actingAs($this->other)
            ->delete(route('folders.destroy', $folder))
            ->assertForbidden();

        $this->assertDatabaseHas('document_folders', ['id' => $folder->id, 'name' => 'Rahasia', 'trashed_at' => null]);
    }

    public function test_dokumen_yang_terlihat_tetapi_bukan_milik_pengguna_tidak_dapat_dimasukkan_ke_folder_pribadi(): void
    {
        $folder = DocumentFolder::create(['owner_id' => $this->other->id, 'name' => 'Milik Saya', 'name_normalized' => 'milik saya']);

        $this->actingAs($this->other)
            ->put(route('documents.folder', $this->document), ['folder_id' => $folder->id])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseMissing('document_placements', [
            'owner_id' => $this->other->id,
            'document_id' => $this->document->id,
        ]);
    }

    public function test_folder_tingkat_keenam_ditolak(): void
    {
        $parent = null;

        foreach (range(1, 5) as $tingkat) {
            $this->actingAs($this->owner)
                ->post(route('folders.store'), ['name' => "Tingkat {$tingkat}", 'parent_id' => $parent?->id])
                ->assertRedirect();
            $parent = DocumentFolder::query()->where('name', "Tingkat {$tingkat}")->sole();
        }

        $this->actingAs($this->owner)
            ->post(route('folders.store'), ['name' => 'Tingkat 6', 'parent_id' => $parent->id])
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('document_folders', ['owner_id' => $this->owner->id, 'name' => 'Tingkat 6']);
    }

    public function test_bintang_dan_terbaru_bersifat_per_pengguna(): void
    {
        $this->actingAs($this->other)->get(route('documents.show', $this->document))->assertOk();
        $this->actingAs($this->other)->put(route('documents.star', $this->document))->assertRedirect();

        $this->assertDatabaseHas('document_recents', ['user_id' => $this->other->id, 'document_id' => $this->document->id]);
        $this->assertDatabaseHas('document_stars', ['user_id' => $this->other->id, 'document_id' => $this->document->id]);
        $this->assertDatabaseMissing('document_stars', ['user_id' => $this->owner->id, 'document_id' => $this->document->id]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => ActivityLogName::DocumentWorkspace->value,
            'event' => AuditEvent::DocumentStarred->value,
            'subject_id' => $this->document->id,
            'causer_id' => $this->other->id,
        ]);

        $this->actingAs($this->other)->delete(route('documents.unstar', $this->document))->assertRedirect();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => ActivityLogName::DocumentWorkspace->value,
            'event' => AuditEvent::DocumentUnstarred->value,
            'subject_id' => $this->document->id,
            'causer_id' => $this->other->id,
        ]);
    }

    public function test_mutasi_workspace_dibatasi_per_pengguna(): void
    {
        foreach (range(1, 30) as $_) {
            $this->actingAs($this->owner)
                ->put(route('documents.star', $this->document))
                ->assertRedirect();
        }

        $this->actingAs($this->owner)
            ->put(route('documents.star', $this->document))
            ->assertTooManyRequests();
    }

    public function test_membuang_dokumen_menyembunyikannya_dan_dapat_dipulihkan(): void
    {
        $this->actingAs($this->owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('documents.destroy', $this->document))
            ->assertRedirect(route('documents.trash'));

        $this->assertNotNull($this->document->fresh()->trashed_at);
        $this->actingAs($this->owner)->get(route('documents.show', $this->document))->assertForbidden();
        $this->actingAs($this->owner)
            ->get('/activity-log')
            ->assertInertia(fn ($page) => $page
                ->where('aktivitas.total', 1)
                ->where('aktivitas.data.0.event', AuditEvent::DocumentTrashed->value));

        $this->actingAs($this->owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('documents.restore-trash', $this->document))
            ->assertRedirect(route('documents.show', $this->document));

        $this->assertNull($this->document->fresh()->trashed_at);
    }

    public function test_perintah_purge_menghapus_berkas_dan_seluruh_rantai_yang_melewati_retensi(): void
    {
        $this->document->update([
            'file_path' => 'documents/purge.pdf',
            'trashed_at' => now()->subDays(31),
            'trashed_by' => $this->owner->id,
            'purge_after' => now()->subSecond(),
            'trash_token' => 'b9701e5f-ecf0-46b8-9a20-2a76e59348e2',
        ]);
        Storage::disk('local')->put('documents/purge.pdf', 'isi uji');

        $this->artisan('documents:purge-trash')->assertSuccessful();

        $this->assertDatabaseMissing('documents', ['id' => $this->document->id]);
        Storage::disk('local')->assertMissing('documents/purge.pdf');
    }

    public function test_editor_membuat_subfolder_di_pohon_orang_lain(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $induk = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $induk->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);

        $subfolder = app(DocumentWorkspaceService::class)->createFolder($editor, $induk, 'Sub oleh editor');

        $this->assertSame($pemilik->id, $subfolder->owner_id, 'subfolder mewarisi pohon pemilik, bukan editor');
        $this->assertSame($induk->id, $subfolder->parent_id);
    }

    public function test_editor_root_folder_tetap_pohon_sendiri(): void
    {
        $editor = User::factory()->create();

        $folder = app(DocumentWorkspaceService::class)->createFolder($editor, null, 'Root milik editor');

        $this->assertSame($editor->id, $folder->owner_id);
    }

    public function test_viewer_tidak_bisa_membuat_subfolder(): void
    {
        $pemilik = User::factory()->create();
        $viewer = User::factory()->create();
        $induk = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $induk->sharedUsers()->attach($viewer->id, ['role' => 'viewer', 'granted_by' => $pemilik->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(DocumentWorkspaceService::class)->createFolder($viewer, $induk, 'Percobaan');
    }

    public function test_nama_subfolder_editor_bentrok_dicek_pada_pohon_pemilik(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $induk = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $induk->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);
        DocumentFolder::factory()->for($pemilik, 'owner')->create(['parent_id' => $induk->id, 'name' => 'Rapat', 'name_normalized' => 'rapat']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(DocumentWorkspaceService::class)->createFolder($editor, $induk, 'Rapat');
    }

    public function test_editor_boleh_mengubah_nama_folder(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create(['name' => 'Lama', 'name_normalized' => 'lama']);
        $folder->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);

        app(DocumentWorkspaceService::class)->renameFolder($folder, $editor, 'Baru');

        $this->assertSame('Baru', $folder->fresh()->name);
        $this->assertDatabaseHas('activity_log', ['subject_id' => $folder->id, 'causer_id' => $editor->id]);
    }

    public function test_viewer_tidak_boleh_mengubah_nama_folder(): void
    {
        $pemilik = User::factory()->create();
        $viewer = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $folder->sharedUsers()->attach($viewer->id, ['role' => 'viewer', 'granted_by' => $pemilik->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(DocumentWorkspaceService::class)->renameFolder($folder, $viewer, 'Percobaan');
    }

    public function test_editor_menaruh_dokumennya_sendiri_ke_folder_pemilik(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $folder->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);
        $dokumen = Document::factory()->create(['uploaded_by' => $editor->id]);

        app(DocumentWorkspaceService::class)->placeDocument($dokumen, $folder, $editor);

        $this->assertDatabaseHas('document_placements', [
            'document_id' => $dokumen->id,
            'folder_id' => $folder->id,
            'owner_id' => $pemilik->id,
        ]);
    }

    public function test_editor_tidak_bisa_menaruh_dokumen_orang_lain(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $folder->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);
        $dokumen = Document::factory()->create(['uploaded_by' => $pemilik->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(DocumentWorkspaceService::class)->placeDocument($dokumen, $folder, $editor);
    }

    public function test_editor_memindahkan_dokumennya_ke_akar_pohon_pemilik(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create();
        $folder->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);
        $dokumen = Document::factory()->create(['uploaded_by' => $editor->id]);
        DocumentPlacement::create(['owner_id' => $pemilik->id, 'document_id' => $dokumen->id, 'folder_id' => $folder->id]);

        app(DocumentWorkspaceService::class)->moveToRoot($dokumen, $folder, $editor);

        $this->assertDatabaseMissing('document_placements', ['document_id' => $dokumen->id, 'owner_id' => $pemilik->id]);
    }
}
