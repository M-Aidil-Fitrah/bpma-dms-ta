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
use App\Services\ActivityLogQuery;
use App\Services\ActivityLogService;
use App\Services\DocumentWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Batas akses riwayat FEAT-15 tidak boleh bocor lewat query atau tab detail. */
final class ActivityLogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $anggota;

    private User $pemilikLain;

    private User $superadmin;

    private Document $terlihat;

    private Document $tertutup;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([User::ROLE_PENGGUNA, User::ROLE_SUPERADMIN] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $jabatan = Jabatan::factory()->tingkat(4)->create();
        $unit = Unit::factory()->create();
        $this->anggota = $this->buatPengguna($jabatan, $unit, 'Anggota Terbatas');
        $this->pemilikLain = $this->buatPengguna($jabatan, $unit, 'Pemilik Lain');
        $this->superadmin = User::factory()->create(['jabatan_id' => null, 'unit_id' => null]);
        $this->superadmin->assignRole(User::ROLE_SUPERADMIN);

        $this->terlihat = Document::factory()->dibagikanKeSemua()->create([
            'judul' => 'Dokumen Terlihat',
            'uploaded_by' => $this->pemilikLain->id,
        ]);
        $this->tertutup = Document::factory()->create([
            'judul' => 'Dokumen Tertutup',
            'uploaded_by' => $this->pemilikLain->id,
        ]);

        $log = app(ActivityLogService::class);
        $log->record(ActivityLogName::Dokumen, AuditEvent::DocumentUpdated, 'Dokumen terlihat diperbarui.', $this->terlihat, $this->pemilikLain);
        $log->record(ActivityLogName::Dokumen, AuditEvent::DocumentUpdated, 'Dokumen tertutup diperbarui.', $this->tertutup, $this->pemilikLain);
        $log->record(ActivityLogName::Kategori, AuditEvent::Created, 'Kategori audit ditambahkan.', Category::factory()->create(), $this->superadmin);
    }

    public function test_pengguna_biasa_hanya_melihat_aktivitas_dokumen_yang_dapat_dibukanya(): void
    {
        $this->actingAs($this->anggota)
            ->get('/activity-log')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ActivityLog/Index')
                ->where('aktivitas.total', 1)
                ->where('aktivitas.data.0.subjek', 'Dokumen Terlihat'));

        $this->get("/documents/{$this->terlihat->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('riwayat', 1));
    }

    public function test_superadmin_melihat_semua_aktivitas_termasuk_subjek_non_dokumen_dan_filternya(): void
    {
        $this->actingAs($this->superadmin)
            ->get('/activity-log?jenis=kategori')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('aktivitas.total', 1)
                ->where('aktivitas.data.0.log_name', ActivityLogName::Kategori->value));

        $this->get('/activity-log?cari=tertutup')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('aktivitas.total', 1)
                ->where('aktivitas.data.0.subjek', 'Dokumen Tertutup'));
    }

    public function test_pemilik_melihat_aktivitas_ruang_kerja_pribadinya_tanpa_membuka_aktivitas_pengguna_lain(): void
    {
        $folder = DocumentFolder::query()->create([
            'owner_id' => $this->anggota->id,
            'name' => 'Arsip Kerja',
            'name_normalized' => 'arsip kerja',
        ]);
        app(DocumentWorkspaceService::class)->trashFolder($folder, $this->anggota);

        $this->actingAs($this->anggota)
            ->get('/activity-log?jenis=document_workspace')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('aktivitas.total', 1)
                ->where('aktivitas.data.0.event', AuditEvent::FolderTrashed->value)
                ->where('aktivitas.data.0.subjek', 'Arsip Kerja'));
    }

    /**
     * Jejak berbagi folder ditulis dengan log name tersendiri. Tanpa keduanya
     * ikut diizinkan, penyaring "Akses folder ..." pada halaman Riwayat
     * Aktivitas selalu kosong — bahkan bagi pemilik yang melakukannya.
     */
    public function test_pemilik_melihat_jejak_berbagi_foldernya_tanpa_membukanya_ke_penerima(): void
    {
        $folder = DocumentFolder::query()->create([
            'owner_id' => $this->anggota->id,
            'name' => 'Arsip Dibagikan',
            'name_normalized' => 'arsip dibagikan',
        ]);

        $this->actingAs($this->anggota)
            ->put(route('folders.share', $folder), ['shared_user_ids' => [$this->pemilikLain->id]])
            ->assertRedirect();

        $this->actingAs($this->anggota)
            ->get('/activity-log?jenis='.ActivityLogName::FolderShare->value)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('aktivitas.total', 1)
                ->where('aktivitas.data.0.event', AuditEvent::AccessGranted->value)
                ->where('aktivitas.data.0.subjek', 'Arsip Dibagikan'));

        // Jejaknya tetap milik pelakunya sendiri: penerima share tidak boleh
        // tahu siapa lagi yang diberi akses ke folder yang sama.
        $this->actingAs($this->pemilikLain)
            ->get('/activity-log?jenis='.ActivityLogName::FolderShare->value)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('aktivitas.total', 0));
    }

    public function test_jumlah_query_riwayat_tidak_bertambah_seiring_aktivitas(): void
    {
        $this->actingAs($this->superadmin);

        // Permintaan pertama turut memanaskan autentikasi dan cache role.
        $this->hitungQueryRiwayat();

        $queryDenganSedikitAktivitas = $this->hitungQueryRiwayat();

        $log = app(ActivityLogService::class);

        foreach (range(1, 40) as $urutan) {
            $log->record(
                ActivityLogName::Dokumen,
                AuditEvent::DocumentUpdated,
                "Aktivitas tambahan {$urutan}.",
                $this->terlihat,
                $this->pemilikLain,
            );
        }

        $queryDenganBanyakAktivitas = $this->hitungQueryRiwayat();

        $this->assertSame($queryDenganSedikitAktivitas, $queryDenganBanyakAktivitas);
    }

    public function test_riwayat_dokumen_merangkum_semua_versi_untuk_tampilan_bertahap(): void
    {
        $terbaru = Document::factory()->dibagikanKeSemua()->create([
            'replaces_document_id' => $this->terlihat->id,
            'version_root_id' => $this->terlihat->version_root_id,
            'version_major' => 2,
            'version_minor' => 0,
            'uploaded_by' => $this->pemilikLain->id,
        ]);
        $log = app(ActivityLogService::class);

        foreach (range(1, 25) as $urutan) {
            $log->record(
                ActivityLogName::Dokumen,
                AuditEvent::DocumentUpdated,
                "Revisi metadata {$urutan}.",
                $this->terlihat,
                $this->pemilikLain,
            );
        }
        $log->record(
            ActivityLogName::Dokumen,
            AuditEvent::DocumentReplaced,
            'Versi terbaru dibuat.',
            $terbaru,
            $this->pemilikLain,
        );

        $this->actingAs($this->anggota)
            ->get("/documents/{$terbaru->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('riwayat', 27)
                ->where('riwayat.0.event', AuditEvent::DocumentReplaced->value)
                ->where('riwayat.1.event', AuditEvent::DocumentUpdated->value));

        $this->get("/documents/{$terbaru->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('riwayat', 27));
    }

    public function test_pemilik_folder_melihat_aktivitas_editor_di_foldernya(): void
    {
        $pemilik = User::factory()->create();
        $editor = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create(['name' => 'Arsip Editor', 'name_normalized' => 'arsip editor']);
        $folder->sharedUsers()->attach($editor->id, ['role' => 'editor', 'granted_by' => $pemilik->id]);

        app(DocumentWorkspaceService::class)->renameFolder($folder, $editor, 'X');

        $terlihat = collect(app(ActivityLogQuery::class)->latestFor($pemilik, 20));

        $this->assertTrue(
            $terlihat->contains(fn ($baris) => str_contains($baris->deskripsi, 'Nama folder diubah') || $baris->subjek === 'X'),
            'pemilik folder harus melihat rename yang dilakukan editor pada foldernya',
        );
    }

    public function test_penerima_share_bukan_pemilik_tidak_melihat_aktivitas_pemilik(): void
    {
        $pemilik = User::factory()->create();
        $viewer = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create(['name' => 'Arsip Milik', 'name_normalized' => 'arsip milik']);
        $folder->sharedUsers()->attach($viewer->id, ['role' => 'viewer', 'granted_by' => $pemilik->id]);

        app(DocumentWorkspaceService::class)->renameFolder($folder, $pemilik, 'Diubah Pemilik');

        $terlihat = collect(app(ActivityLogQuery::class)->latestFor($viewer, 20));

        $this->assertFalse(
            $terlihat->contains(fn ($baris) => str_contains($baris->deskripsi, 'Nama folder diubah')),
            'penerima share yang bukan pemilik tidak boleh melihat aksi folder pemilik',
        );
    }

    public function test_pengguna_tak_terkait_tidak_melihat_aktivitas_folder_orang_lain(): void
    {
        $pemilik = User::factory()->create();
        $orang = User::factory()->create();
        $folder = DocumentFolder::factory()->for($pemilik, 'owner')->create(['name' => 'Arsip Tertutup', 'name_normalized' => 'arsip tertutup']);

        app(DocumentWorkspaceService::class)->renameFolder($folder, $pemilik, 'Rahasia');

        $terlihat = collect(app(ActivityLogQuery::class)->latestFor($orang, 20));

        $this->assertFalse(
            $terlihat->contains(fn ($baris) => str_contains($baris->deskripsi, 'Nama folder diubah')),
            'pengguna tak terkait tidak boleh melihat aktivitas folder orang lain',
        );
    }

    private function hitungQueryRiwayat(): int
    {
        $jumlahQuery = 0;

        DB::listen(static function () use (&$jumlahQuery): void {
            $jumlahQuery++;
        });

        $this->get('/activity-log')->assertOk();

        return $jumlahQuery;
    }

    private function buatPengguna(Jabatan $jabatan, Unit $unit, string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'jabatan_id' => $jabatan->id, 'unit_id' => $unit->id]);
        $user->assignRole(User::ROLE_PENGGUNA);

        return $user;
    }
}
