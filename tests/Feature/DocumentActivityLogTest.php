<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Enums\DocumentEditScope;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Category;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Jejak aksi dokumen FEAT-15 — termasuk target akses yang sudah dicabut. */
final class DocumentActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $pemilik;

    private User $superadmin;

    private User $penerimaLama;

    private User $penerimaBaru;

    private Category $kategori;

    private Unit $unitLama;

    private Unit $unitBaru;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([User::ROLE_PENGGUNA, User::ROLE_SUPERADMIN] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $jabatan = Jabatan::factory()->tingkat(4)->create();
        $this->kategori = Category::factory()->create();
        $this->unitLama = Unit::factory()->create(['nama' => 'Divisi Akses Lama']);
        $this->unitBaru = Unit::factory()->create(['nama' => 'Divisi Akses Baru']);

        $this->pemilik = $this->buatPengguna($jabatan, $this->unitLama, 'Pemilik Dokumen');
        $this->penerimaLama = $this->buatPengguna($jabatan, $this->unitLama, 'Penerima Lama');
        $this->penerimaBaru = $this->buatPengguna($jabatan, $this->unitBaru, 'Penerima Baru');

        $this->superadmin = User::factory()->create(['jabatan_id' => null, 'unit_id' => null]);
        $this->superadmin->assignRole(User::ROLE_SUPERADMIN);
    }

    public function test_unggah_merekam_seluruh_mekanisme_akses_dalam_satu_aktivitas(): void
    {
        Queue::fake();

        $this->actingAs($this->pemilik)
            ->post('/documents', [
                'nomor' => '001/BPMA/AUDIT/VIII/2026',
                'judul' => 'Dokumen Yang Diunggah',
                'category_id' => $this->kategori->id,
                'origin_unit_id' => $this->unitLama->id,
                'tanggal' => '2026-08-16',
                'edit_scope' => DocumentEditScope::OwnerOnly->value,
                'file' => UploadedFile::fake()->create('audit.pdf', 100, 'application/pdf'),
                'is_shared_to_all' => false,
                'min_tingkat_akses' => 4,
                'unit_ids' => [$this->unitLama->id],
                'shared_user_ids' => [$this->penerimaLama->id],
            ])
            ->assertRedirect();

        Queue::assertPushed(ExtractDocumentTextJob::class);

        $activity = Activity::query()->sole();

        $this->assertSame(ActivityLogName::Dokumen->value, $activity->log_name);
        $this->assertSame(AuditEvent::DocumentUploaded->value, $activity->event);
        $this->assertSame($this->pemilik->id, $activity->causer_id);
        $this->assertFalse($activity->getProperty('mekanisme_akses.dibagikan_ke_semua'));
        $this->assertSame('Tingkat 4 ke atas', $activity->getProperty('mekanisme_akses.jenjang_jabatan'));
        $this->assertSame('Divisi Akses Lama', $activity->getProperty('mekanisme_akses.unit.0.nama'));
        $this->assertSame('Penerima Lama', $activity->getProperty('mekanisme_akses.orang_tertentu.0.nama'));
    }

    public function test_penggantian_berkas_mencatat_relasi_pada_versi_lama_dan_baru(): void
    {
        Queue::fake();
        $versiLama = $this->buatDokumen();

        $this->actingAs($this->pemilik)
            ->post('/documents', [
                'nomor' => '002/BPMA/AUDIT/VIII/2026',
                'judul' => 'Dokumen Versi Baru',
                'category_id' => $this->kategori->id,
                'origin_unit_id' => $this->unitLama->id,
                'tanggal' => '2026-08-17',
                'edit_scope' => DocumentEditScope::OwnerOnly->value,
                'file' => UploadedFile::fake()->create('audit-baru.pdf', 100, 'application/pdf'),
                'is_shared_to_all' => true,
                'replaces_document_id' => $versiLama->id,
                'version_note' => 'Memperbarui isi dokumen.',
            ])
            ->assertRedirect();

        $versiBaru = Document::firstWhere('judul', 'Dokumen Versi Baru');

        $this->assertFalse($versiLama->fresh()->is_active);
        $this->assertTrue($versiBaru->is_active);
        $this->assertSame($versiLama->id, $versiBaru->replaces_document_id);
        $this->assertSame([
            $versiLama->id,
            $versiBaru->id,
        ], Activity::query()
            ->where('event', AuditEvent::DocumentReplaced->value)
            ->orderBy('subject_id')
            ->pluck('subject_id')
            ->all());
    }

    public function test_ubah_metadata_dan_target_akses_mencatat_before_after_serta_setiap_pencabutan(): void
    {
        $document = $this->buatDokumen();
        $document->targetUnits()->attach($this->unitLama->id, ['added_by' => $this->pemilik->id]);
        $document->sharedUsers()->attach($this->penerimaLama->id, ['granted_by' => $this->pemilik->id]);

        $this->actingAs($this->pemilik)
            ->patch("/documents/{$document->id}", $this->formulirUbah([
                'judul' => 'Judul Sesudah Diubah',
                'unit_ids' => [$this->unitBaru->id],
                'shared_user_ids' => [$this->penerimaBaru->id],
            ]))
            ->assertRedirect();

        $metadata = Activity::query()->where('event', AuditEvent::DocumentUpdated->value)->sole();

        $this->assertSame('Dokumen Awal', $metadata->attribute_changes->get('old')['Judul']);
        $this->assertSame('Judul Sesudah Diubah', $metadata->attribute_changes->get('attributes')['Judul']);
        $this->assertSame(5, Activity::count());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => ActivityLogName::DocumentUnit->value,
            'event' => AuditEvent::AccessGranted->value,
            'description' => 'Akses unit "Divisi Akses Baru" ditambahkan.',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => ActivityLogName::DocumentUnit->value,
            'event' => AuditEvent::AccessRevoked->value,
            'description' => 'Akses unit "Divisi Akses Lama" dicabut.',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => ActivityLogName::DocumentShare->value,
            'event' => AuditEvent::AccessGranted->value,
            'description' => 'Akses untuk "Penerima Baru" ditambahkan.',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => ActivityLogName::DocumentShare->value,
            'event' => AuditEvent::AccessRevoked->value,
            'description' => 'Akses untuk "Penerima Lama" dicabut.',
        ]);
    }

    public function test_unduh_nonaktifkan_dan_aktifkan_kembali_dicatat_dengan_pelaku_sebenarnya(): void
    {
        $document = $this->buatDokumen();
        Storage::disk('local')->put($document->file_path, 'isi berkas uji');

        $this->actingAs($this->pemilik)->get("/documents/{$document->id}/file")->assertOk();
        $this->actingAs($this->pemilik)->delete("/documents/{$document->id}")->assertRedirect();
        $this->actingAs($this->superadmin)->patch("/documents/{$document->id}/restore")->assertRedirect();

        $this->assertSame([
            AuditEvent::DocumentDownloaded->value,
            AuditEvent::DocumentDeactivated->value,
            AuditEvent::DocumentRestored->value,
        ], Activity::query()->orderBy('id')->pluck('event')->all());
        $this->assertSame($this->pemilik->id, Activity::query()->orderBy('id')->first()->causer_id);
        $this->assertSame($this->superadmin->id, Activity::query()->orderByDesc('id')->first()->causer_id);
    }

    public function test_membuka_halaman_detail_tidak_menambah_riwayat_aktivitas(): void
    {
        $document = $this->buatDokumen();

        $this->actingAs($this->pemilik)
            ->get("/documents/{$document->id}")
            ->assertOk();

        $this->assertSame(0, Activity::query()->count());
    }

    private function buatDokumen(): Document
    {
        return Document::factory()->create([
            'judul' => 'Dokumen Awal',
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->unitLama->id,
            'uploaded_by' => $this->pemilik->id,
        ]);
    }

    /** @param array<string, mixed> $ubah @return array<string, mixed> */
    private function formulirUbah(array $ubah = []): array
    {
        return [
            'nomor' => '001/BPMA/AUDIT/VIII/2026',
            'judul' => 'Dokumen Awal',
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->unitLama->id,
            'tanggal' => '2026-08-16',
            'edit_scope' => DocumentEditScope::OwnerOnly->value,
            'is_shared_to_all' => false,
            'unit_ids' => [$this->unitLama->id],
            'shared_user_ids' => [$this->penerimaLama->id],
            'version_note' => 'Memperbarui metadata dan akses.',
            ...$ubah,
        ];
    }

    private function buatPengguna(Jabatan $jabatan, Unit $unit, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'jabatan_id' => $jabatan->id,
            'unit_id' => $unit->id,
        ]);
        $user->assignRole(User::ROLE_PENGGUNA);

        return $user;
    }
}
