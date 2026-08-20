<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Enums\DocumentEditScope;
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

/** Riwayat versi linear: snapshot, pratinjau, format, dan pemulihan. */
final class DocumentVersionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $pemilik;

    private Unit $unit;

    private Category $kategori;

    private Document $awal;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(User::ROLE_PENGGUNA, 'web');
        Role::findOrCreate(User::ROLE_SUPERADMIN, 'web');
        $this->unit = Unit::factory()->create();
        $this->kategori = Category::factory()->create();
        $this->pemilik = $this->buatPengguna($this->unit, 'Pemilik Rantai');
        $this->awal = Document::factory()->create([
            'judul' => 'Snapshot Awal',
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->unit->id,
            'uploaded_by' => $this->pemilik->id,
        ]);
        $this->awal->targetUnits()->attach($this->unit->id, ['added_by' => $this->pemilik->id]);
    }

    public function test_perubahan_metadata_membuat_minor_dan_menjaga_snapshot_lama(): void
    {
        $jalurAwal = $this->awal->file_path;

        $this->actingAs($this->pemilik)
            ->patch("/documents/{$this->awal->id}", $this->formulir([
                'judul' => 'Snapshot Metadata Baru',
                'version_note' => 'Memperbaiki judul dokumen.',
            ]))
            ->assertRedirect();

        $baru = $this->versiTerbaru();
        $this->assertSame('v1.1', $baru->versionLabel());
        $this->assertSame($jalurAwal, $baru->file_path);
        $this->assertSame($this->awal->id, $baru->replaces_document_id);
        $this->assertFalse($this->awal->fresh()->is_active);
        $this->assertSame('Snapshot Awal', $this->awal->fresh()->judul);
        $this->assertSame('Snapshot Metadata Baru', $baru->judul);

        $this->actingAs($this->pemilik)
            ->get("/documents/{$this->awal->id}")
            ->assertOk()
            ->assertInertia(fn ($halaman) => $halaman
                ->where('dokumen.version_label', 'v1.0')
                ->where('versi.0.label', 'v1.1')
                ->where('versi.1.label', 'v1.0'));
    }

    public function test_berkas_pengganti_hanya_boleh_format_sama_dan_membuat_major(): void
    {
        Queue::fake();

        $this->actingAs($this->pemilik)
            ->post('/documents', $this->formulir([
                'file' => UploadedFile::fake()->create('revisi.pdf', 10, 'application/pdf'),
                'replaces_document_id' => $this->awal->id,
                'version_note' => 'Mengganti isi yang telah diperbarui.',
            ]))
            ->assertRedirect();

        $baru = $this->versiTerbaru();
        $this->assertSame('v2.0', $baru->versionLabel());
        $this->assertSame('application/pdf', $baru->file_mime_type);
        $this->assertNotSame($this->awal->file_path, $baru->file_path);
        $this->assertFalse($this->awal->fresh()->is_active);

        $lain = Document::factory()->create([
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->unit->id,
            'uploaded_by' => $this->pemilik->id,
        ]);
        $lain->targetUnits()->attach($this->unit->id, ['added_by' => $this->pemilik->id]);

        $this->actingAs($this->pemilik)
            ->post('/documents', $this->formulir([
                'file' => UploadedFile::fake()->create('berbeda.txt', 10, 'text/plain'),
                'replaces_document_id' => $lain->id,
                'version_note' => 'Mencoba format yang berbeda.',
            ]))
            ->assertSessionHasErrors('file');

        $this->assertTrue($lain->fresh()->is_active);
        $this->assertDatabaseCount('documents', 3);
    }

    public function test_hak_pemilik_memulihkan_arsip_menjadi_major_terbaru_dan_mencatat_log(): void
    {
        $this->actingAs($this->pemilik)
            ->patch("/documents/{$this->awal->id}", $this->formulir([
                'judul' => 'Versi Yang Keliru',
                'version_note' => 'Perubahan yang keliru.',
            ]))
            ->assertRedirect();

        $versiKeliru = $this->versiTerbaru();
        $this->actingAs($this->pemilik)
            ->post("/documents/{$this->awal->id}/restore-version", [
                'version_note' => 'Kembali ke isi snapshot awal.',
            ])
            ->assertRedirect();

        $pulihan = $this->versiTerbaru();
        $this->assertSame('v2.0', $pulihan->versionLabel());
        $this->assertSame('Snapshot Awal', $pulihan->judul);
        $this->assertFalse($versiKeliru->fresh()->is_active);
        $this->assertFalse($this->awal->fresh()->is_active);
        $this->assertSame('v1.0', $this->awal->fresh()->versionLabel());
        $this->assertDatabaseHas('activity_log', [
            'event' => AuditEvent::DocumentVersionRestored->value,
            'subject_id' => $pulihan->id,
        ]);
    }

    public function test_pengguna_yang_berhak_melihat_versi_terbaru_dapat_membuka_arsip_sampai_aksesnya_dicabut(): void
    {
        $pembaca = $this->buatPengguna($this->unit, 'Pembaca Arsip');
        Storage::disk('local')->put($this->awal->file_path, 'isi arsip uji');

        $this->actingAs($this->pemilik)
            ->patch("/documents/{$this->awal->id}", $this->formulir([
                'judul' => 'Versi Dengan Akses Pembaca',
                'version_note' => 'Membuat snapshot yang masih dapat dibaca.',
            ]))
            ->assertRedirect();

        $this->actingAs($pembaca)
            ->get("/documents/{$this->awal->id}")
            ->assertOk();
        $this->actingAs($pembaca)
            ->get("/documents/{$this->awal->id}/file")
            ->assertOk();

        $unitLain = Unit::factory()->create();
        $terbaru = $this->versiTerbaru();
        $this->actingAs($this->pemilik)
            ->patch("/documents/{$terbaru->id}", $this->formulir([
                'unit_ids' => [$unitLain->id],
                'version_note' => 'Mencabut akses unit sebelumnya.',
            ]))
            ->assertRedirect();

        $this->actingAs($pembaca)
            ->get("/documents/{$this->awal->id}")
            ->assertForbidden();
    }

    public function test_riwayat_versi_mengirim_semua_entri_untuk_tampilan_bertahap(): void
    {
        $this->awal->update(['is_active' => false]);

        foreach (range(2, 6) as $major) {
            $terbaru = Document::factory()->create([
                'category_id' => $this->kategori->id,
                'origin_unit_id' => $this->unit->id,
                'uploaded_by' => $this->pemilik->id,
                'version_root_id' => $this->awal->version_root_id,
                'version_major' => $major,
                'version_minor' => 0,
                'is_active' => $major === 6,
            ]);
        }

        $this->actingAs($this->pemilik)
            ->get("/documents/{$terbaru->id}")
            ->assertInertia(fn ($halaman) => $halaman
                ->has('versi', 6)
                ->where('versi.0.label', 'v6.0')
                ->where('versi.0.terbaru', true));
    }

    public function test_editor_bukan_pemilik_rantai_tidak_dapat_memulihkan_versi(): void
    {
        $editor = $this->buatPengguna($this->unit, 'Editor Rantai');
        $this->awal->update(['edit_scope' => DocumentEditScope::MatchVisibility]);

        $this->actingAs($editor)
            ->post("/documents/{$this->awal->id}/restore-version", [
                'version_note' => 'Tidak memiliki hak pemulihan.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('documents', 1);
        $this->assertSame(0, Activity::count());
    }

    /** @param array<string, mixed> $ubah @return array<string, mixed> */
    private function formulir(array $ubah = []): array
    {
        return [
            'nomor' => $this->awal->nomor,
            'judul' => $this->awal->judul,
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->unit->id,
            'tanggal' => $this->awal->tanggal->toDateString(),
            'edit_scope' => DocumentEditScope::OwnerOnly->value,
            'unit_ids' => [$this->unit->id],
            ...$ubah,
        ];
    }

    private function versiTerbaru(): Document
    {
        return Document::query()
            ->where('version_root_id', $this->awal->version_root_id)
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->firstOrFail();
    }

    private function buatPengguna(Unit $unit, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'jabatan_id' => Jabatan::factory()->tingkat(4)->create()->id,
            'unit_id' => $unit->id,
        ]);
        $user->assignRole(User::ROLE_PENGGUNA);

        return $user;
    }
}
