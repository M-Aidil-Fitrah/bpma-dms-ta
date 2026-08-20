<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
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
        $this->assertSame('Laporan 2026', Activity::query()->sole()->getProperty('lokasi_tujuan'));
    }

    public function test_pengguna_lain_tidak_dapat_melihat_folder_atau_memindahkan_dokumen_milik_pengunggah(): void
    {
        $folder = DocumentFolder::create(['owner_id' => $this->owner->id, 'name' => 'Rahasia', 'name_normalized' => 'rahasia']);

        $this->actingAs($this->other)->get(route('folders.show', $folder))->assertForbidden();
        $this->actingAs($this->other)
            ->put(route('documents.folder', $this->document), ['folder_id' => $folder->id])
            ->assertSessionHasErrors('folder');
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
}
