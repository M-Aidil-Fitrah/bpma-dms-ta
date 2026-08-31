<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Models\Category;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Halaman monitoring superadmin FEAT-15b: lintas pengguna, dengan filter pelaku dan unit. */
final class AdminActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $pelakuA;

    private User $pelakuB;

    private Unit $unitA;

    private Unit $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([User::ROLE_PENGGUNA, User::ROLE_SUPERADMIN] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $jabatan = Jabatan::factory()->tingkat(4)->create();
        $this->unitA = Unit::factory()->create(['nama' => 'Divisi A']);
        $this->unitB = Unit::factory()->create(['nama' => 'Divisi B']);

        $this->pelakuA = $this->buatPengguna($jabatan, $this->unitA, 'Pelaku A');
        $this->pelakuB = $this->buatPengguna($jabatan, $this->unitB, 'Pelaku B');
        $this->superadmin = User::factory()->create(['jabatan_id' => null, 'unit_id' => null]);
        $this->superadmin->assignRole(User::ROLE_SUPERADMIN);

        $dokumenTertutup = Document::factory()->create([
            'judul' => 'Dokumen Tertutup Pelaku A',
            'uploaded_by' => $this->pelakuA->id,
        ]);

        $log = app(ActivityLogService::class);
        $log->record(ActivityLogName::Dokumen, AuditEvent::DocumentUpdated, 'Dokumen tertutup diperbarui.', $dokumenTertutup, $this->pelakuA);
        $log->record(ActivityLogName::Kategori, AuditEvent::Created, 'Kategori ditambahkan.', Category::factory()->create(), $this->pelakuB);
    }

    public function test_pengguna_biasa_tidak_dapat_mengakses_log_aktivitas_admin(): void
    {
        $this->actingAs($this->pelakuA)
            ->get('/admin/activity-log')
            ->assertForbidden();
    }

    public function test_superadmin_melihat_aktivitas_seluruh_pengguna_termasuk_subjek_non_dokumen(): void
    {
        $this->actingAs($this->superadmin)
            ->get('/admin/activity-log')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ActivityLog/Index')
                ->where('aktivitas.total', 2));
    }

    public function test_filter_pelaku_membatasi_aktivitas_ke_pengguna_tersebut(): void
    {
        $this->actingAs($this->superadmin)
            ->get("/admin/activity-log?pelaku={$this->pelakuB->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('aktivitas.total', 1)
                ->where('aktivitas.data.0.pelaku', 'Pelaku B'));
    }

    public function test_filter_unit_membatasi_aktivitas_ke_pengguna_pada_unit_tersebut(): void
    {
        $this->actingAs($this->superadmin)
            ->get("/admin/activity-log?unit={$this->unitA->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('aktivitas.total', 1)
                ->where('aktivitas.data.0.pelaku', 'Pelaku A'));
    }

    public function test_pencarian_pelaku_admin_dibatasi_per_pengguna(): void
    {
        foreach (range(1, 60) as $_) {
            $this->actingAs($this->superadmin)
                ->getJson('/admin/activity-log/cari-pengguna?cari=Pelaku')
                ->assertOk();
        }

        $this->actingAs($this->superadmin)
            ->getJson('/admin/activity-log/cari-pengguna?cari=Pelaku')
            ->assertTooManyRequests();
    }

    private function buatPengguna(Jabatan $jabatan, Unit $unit, string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'jabatan_id' => $jabatan->id, 'unit_id' => $unit->id]);
        $user->assignRole(User::ROLE_PENGGUNA);

        return $user;
    }
}
