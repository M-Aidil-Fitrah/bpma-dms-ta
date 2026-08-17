<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ActivityLogName;
use App\Enums\AuditEvent;
use App\Models\Category;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Aktivitas perubahan admin FEAT-15, termasuk data dinamis dan soft-disable. */
final class AdministrationActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private Jabatan $jabatan;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([User::ROLE_SUPERADMIN, User::ROLE_PENGGUNA] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->jabatan = Jabatan::factory()->tingkat(4)->create(['nama' => 'Analis Awal']);
        $this->unit = Unit::factory()->create(['nama' => 'Unit Awal']);
        $this->superadmin = User::factory()->create(['jabatan_id' => null, 'unit_id' => null]);
        $this->superadmin->assignRole(User::ROLE_SUPERADMIN);
    }

    public function test_manajemen_pengguna_mencatat_seluruh_tindakan_tanpa_menyimpan_rahasia_kata_sandi(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/admin/users', [
                'name' => 'Pengguna Audit',
                'email' => 'pengguna.audit@example.test',
                'password' => 'password-aman',
                'password_confirmation' => 'password-aman',
                'jabatan_id' => $this->jabatan->id,
                'unit_id' => $this->unit->id,
            ])
            ->assertRedirect();

        $user = User::firstWhere('email', 'pengguna.audit@example.test');
        $jabatanBaru = Jabatan::factory()->tingkat(5)->create(['nama' => 'Analis Baru']);

        $this->patch("/admin/users/{$user->id}", [
            'name' => 'Pengguna Audit Diperbarui',
            'email' => $user->email,
            'jabatan_id' => $jabatanBaru->id,
            'unit_id' => $this->unit->id,
        ])->assertRedirect();
        $this->delete("/admin/users/{$user->id}")->assertRedirect();
        $this->patch("/admin/users/{$user->id}/restore")->assertRedirect();
        $this->patch("/admin/users/{$user->id}/password", [
            'password' => 'kata-sandi-baru',
            'password_confirmation' => 'kata-sandi-baru',
        ])->assertRedirect();

        $this->assertSame([
            AuditEvent::Created->value,
            AuditEvent::Updated->value,
            AuditEvent::Deactivated->value,
            AuditEvent::Restored->value,
            AuditEvent::PasswordReset->value,
        ], Activity::query()->orderBy('id')->pluck('event')->all());

        $update = Activity::query()->where('event', AuditEvent::Updated->value)->sole();
        $this->assertSame('Analis Awal', $update->attribute_changes->get('old')['Jabatan']);
        $this->assertSame('Analis Baru', $update->attribute_changes->get('attributes')['Jabatan']);
        $this->assertStringNotContainsString('kata-sandi-baru', $update->toJson());
        $this->assertStringNotContainsString($user->fresh()->password, Activity::query()->toBase()->get()->toJson());
    }

    public function test_kategori_jabatan_dan_unit_mencatat_tambah_ubah_nonaktifkan_serta_pulihkan(): void
    {
        $this->actingAs($this->superadmin);

        $this->post('/admin/categories', ['nama' => 'Kategori Audit', 'deskripsi' => 'Awal'])->assertRedirect();
        $kategori = Category::firstWhere('nama', 'Kategori Audit');
        $this->patch("/admin/categories/{$kategori->id}", ['nama' => 'Kategori Audit Baru', 'deskripsi' => 'Sesudah'])->assertRedirect();
        $this->delete("/admin/categories/{$kategori->id}")->assertRedirect();
        $this->patch("/admin/categories/{$kategori->id}/restore")->assertRedirect();

        $this->post('/admin/jabatans', ['nama' => 'Jabatan Audit', 'tingkat_akses' => 6])->assertRedirect();
        $jabatan = Jabatan::firstWhere('nama', 'Jabatan Audit');
        $this->patch("/admin/jabatans/{$jabatan->id}", ['nama' => 'Jabatan Audit Baru', 'tingkat_akses' => 7])->assertRedirect();
        $this->delete("/admin/jabatans/{$jabatan->id}")->assertRedirect();
        $this->patch("/admin/jabatans/{$jabatan->id}/restore")->assertRedirect();

        $this->post('/admin/units', ['nama' => 'Unit Audit', 'parent_id' => $this->unit->id, 'tipe' => Unit::TIPE_DIVISI])->assertRedirect();
        $unit = Unit::firstWhere('nama', 'Unit Audit');
        $this->patch("/admin/units/{$unit->id}", ['nama' => 'Unit Audit Baru', 'parent_id' => null, 'tipe' => Unit::TIPE_DEPUTI])->assertRedirect();
        $this->delete("/admin/units/{$unit->id}")->assertRedirect();
        $this->patch("/admin/units/{$unit->id}/restore")->assertRedirect();

        $this->assertSame(12, Activity::count());
        $this->assertSame(4, Activity::query()->where('log_name', ActivityLogName::Kategori->value)->count());
        $this->assertSame(4, Activity::query()->where('log_name', ActivityLogName::Jabatan->value)->count());
        $this->assertSame(4, Activity::query()->where('log_name', ActivityLogName::Unit->value)->count());

        $unitUpdate = Activity::query()
            ->where('log_name', ActivityLogName::Unit->value)
            ->where('event', AuditEvent::Updated->value)
            ->sole();
        $this->assertSame('Unit Awal', $unitUpdate->attribute_changes->get('old')['Unit induk']);
        $this->assertSame('Tidak ada', $unitUpdate->attribute_changes->get('attributes')['Unit induk']);
    }

    public function test_pengguna_yang_mengubah_profil_sendiri_tetap_memiliki_jejak_before_after(): void
    {
        $emailLama = $this->superadmin->email;

        $this->actingAs($this->superadmin)
            ->patch('/profile', [
                'name' => 'Superadmin Diperbarui',
                'email' => 'superadmin.diperbarui@example.test',
            ])
            ->assertRedirect(route('profile.edit'));

        $activity = Activity::query()->sole();

        $this->assertSame(ActivityLogName::Pengguna->value, $activity->log_name);
        $this->assertSame(AuditEvent::Updated->value, $activity->event);
        $this->assertSame($this->superadmin->id, $activity->subject_id);
        $this->assertSame($this->superadmin->id, $activity->causer_id);
        $this->assertSame($emailLama, $activity->attribute_changes->get('old')['Surel']);
        $this->assertSame('superadmin.diperbarui@example.test', $activity->attribute_changes->get('attributes')['Surel']);
    }
}
