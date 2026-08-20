<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Manajemen pengguna — halaman khusus Superadmin (FR-25 s.d. FR-27, FR-31).
 *
 * Tidak ada registrasi publik, jadi ini satu-satunya jalan sebuah akun
 * `pengguna` bisa terbentuk.
 */
final class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $anggota;

    private Jabatan $jabatan;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([User::ROLE_SUPERADMIN, User::ROLE_PENGGUNA] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->jabatan = Jabatan::factory()->tingkat(4)->create();
        $this->unit = Unit::factory()->create();

        $this->superadmin = User::factory()->create(['jabatan_id' => null, 'unit_id' => null]);
        $this->superadmin->assignRole(User::ROLE_SUPERADMIN);

        $this->anggota = User::factory()->create([
            'jabatan_id' => $this->jabatan->id,
            'unit_id' => $this->unit->id,
        ]);
        $this->anggota->assignRole(User::ROLE_PENGGUNA);
    }

    // -- Gerbang ----------------------------------------------------------------

    public function test_akun_pengguna_ditolak_membuka_route_admin(): void
    {
        $this->actingAs($this->anggota)->get('/admin/users')->assertForbidden();
    }

    public function test_tamu_dialihkan_ke_halaman_masuk(): void
    {
        $this->get('/admin/users')->assertRedirect(route('login'));
    }

    public function test_superadmin_dapat_membuka_daftar_pengguna(): void
    {
        $this->actingAs($this->superadmin)->get('/admin/users')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Users/Index')
                ->has('pengguna.data', 2));
    }

    public function test_jumlah_query_daftar_pengguna_tidak_bertambah_seiring_data(): void
    {
        $this->actingAs($this->superadmin);

        // Permintaan pertama turut memanaskan autentikasi dan cache role.
        $this->hitungQueryDaftarPengguna();

        $queryDenganSedikitPengguna = $this->hitungQueryDaftarPengguna();

        User::factory()->count(40)->create([
            'jabatan_id' => $this->jabatan->id,
            'unit_id' => $this->unit->id,
        ]);

        $queryDenganBanyakPengguna = $this->hitungQueryDaftarPengguna();

        $this->assertSame($queryDenganSedikitPengguna, $queryDenganBanyakPengguna);
    }

    private function hitungQueryDaftarPengguna(): int
    {
        $jumlahQuery = 0;

        DB::listen(static function () use (&$jumlahQuery): void {
            $jumlahQuery++;
        });

        $this->get('/admin/users')->assertOk();

        return $jumlahQuery;
    }

    // -- Daftar, pencarian, dan filter --------------------------------------------

    public function test_pencarian_mencakup_nama_dan_surel(): void
    {
        User::factory()->create(['name' => 'Seno Wijaya', 'jabatan_id' => $this->jabatan->id, 'unit_id' => $this->unit->id]);
        User::factory()->create(['email' => 'seno.aksara@bpma.internal', 'jabatan_id' => $this->jabatan->id, 'unit_id' => $this->unit->id]);
        User::factory()->create(['name' => 'Rahmat Hidayat', 'jabatan_id' => $this->jabatan->id, 'unit_id' => $this->unit->id]);

        $this->actingAs($this->superadmin)->get('/admin/users?cari=seno')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('pengguna.data', 2));
    }

    public function test_penyaring_jabatan_dan_unit_bekerja(): void
    {
        $jabatanLain = Jabatan::factory()->tingkat(2)->create();
        $unitLain = Unit::factory()->create();

        User::factory()->create(['jabatan_id' => $jabatanLain->id, 'unit_id' => $this->unit->id]);
        User::factory()->create(['jabatan_id' => $this->jabatan->id, 'unit_id' => $unitLain->id]);

        $this->actingAs($this->superadmin);

        $this->get("/admin/users?jabatan={$jabatanLain->id}")
            ->assertInertia(fn (AssertableInertia $p) => $p->has('pengguna.data', 1));

        $this->get("/admin/users?unit={$unitLain->id}")
            ->assertInertia(fn (AssertableInertia $p) => $p->has('pengguna.data', 1));
    }

    public function test_penyaring_status_bekerja(): void
    {
        User::factory()->create([
            'jabatan_id' => $this->jabatan->id,
            'unit_id' => $this->unit->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->superadmin)->get('/admin/users?status=nonaktif')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('pengguna.data', 1));
    }

    // -- Tambah pengguna ----------------------------------------------------------

    /**
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function formulir(array $ubah = []): array
    {
        return [
            'name' => 'Pengguna Baru',
            'email' => 'pengguna.baru@bpma.internal',
            'password' => 'kata-sandi-aman-123',
            'password_confirmation' => 'kata-sandi-aman-123',
            'jabatan_id' => $this->jabatan->id,
            'unit_id' => $this->unit->id,
            ...$ubah,
        ];
    }

    public function test_superadmin_dapat_menambah_pengguna(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/admin/users', $this->formulir())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $pengguna = User::firstWhere('email', 'pengguna.baru@bpma.internal');

        $this->assertNotNull($pengguna);
        $this->assertTrue($pengguna->is_active);
        $this->assertTrue($pengguna->hasRole(User::ROLE_PENGGUNA));
        $this->assertFalse($pengguna->hasRole(User::ROLE_SUPERADMIN));
    }

    public function test_pimpinan_tertinggi_dapat_ditambahkan_tanpa_unit_kerja(): void
    {
        $jabatanTertinggi = Jabatan::factory()->tingkat(1)->create();

        $this->actingAs($this->superadmin)
            ->post('/admin/users', $this->formulir([
                'jabatan_id' => $jabatanTertinggi->id,
                'unit_id' => null,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'pengguna.baru@bpma.internal',
            'jabatan_id' => $jabatanTertinggi->id,
            'unit_id' => null,
        ]);
    }

    public function test_pengguna_nonpimpinan_tetap_wajib_memiliki_unit_kerja(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/admin/users', $this->formulir(['unit_id' => null]))
            ->assertSessionHasErrors('unit_id');
    }

    public function test_pengguna_baru_dapat_langsung_masuk(): void
    {
        $this->actingAs($this->superadmin)->post('/admin/users', $this->formulir());

        $this->post('/logout');

        $this->post('/login', [
            'email' => 'pengguna.baru@bpma.internal',
            'password' => 'kata-sandi-aman-123',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_akun_pengguna_tidak_dapat_menambah_pengguna(): void
    {
        $this->actingAs($this->anggota)->post('/admin/users', $this->formulir())->assertForbidden();
    }

    public function test_surel_wajib_unik(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/admin/users', $this->formulir(['email' => $this->anggota->email]))
            ->assertSessionHasErrors('email');
    }

    public function test_jabatan_nonaktif_tidak_dapat_dipilih(): void
    {
        $jabatanNonaktif = Jabatan::factory()->tingkat(3)->create(['is_active' => false]);

        $this->actingAs($this->superadmin)
            ->post('/admin/users', $this->formulir(['jabatan_id' => $jabatanNonaktif->id]))
            ->assertSessionHasErrors('jabatan_id');
    }

    public function test_unit_nonaktif_tidak_dapat_dipilih(): void
    {
        $unitNonaktif = Unit::factory()->create(['is_active' => false]);

        $this->actingAs($this->superadmin)
            ->post('/admin/users', $this->formulir(['unit_id' => $unitNonaktif->id]))
            ->assertSessionHasErrors('unit_id');
    }

    // -- Ubah pengguna --------------------------------------------------------

    public function test_superadmin_dapat_mengubah_jabatan_dan_unit(): void
    {
        $jabatanBaru = Jabatan::factory()->tingkat(2)->create();
        $unitBaru = Unit::factory()->create();

        $this->actingAs($this->superadmin)
            ->patch("/admin/users/{$this->anggota->id}", [
                'name' => $this->anggota->name,
                'email' => $this->anggota->email,
                'jabatan_id' => $jabatanBaru->id,
                'unit_id' => $unitBaru->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->anggota->refresh();
        $this->assertSame($jabatanBaru->id, $this->anggota->jabatan_id);
        $this->assertSame($unitBaru->id, $this->anggota->unit_id);
    }

    public function test_surel_boleh_tetap_sama_saat_menyunting_diri_sendiri(): void
    {
        // Mengecualikan diri sendiri dari aturan unik — tanpa ini, menyunting
        // profil tanpa mengubah surel akan salah dianggap pelanggaran.
        $this->actingAs($this->superadmin)
            ->patch("/admin/users/{$this->anggota->id}", [
                'name' => 'Nama Diperbarui',
                'email' => $this->anggota->email,
                'jabatan_id' => $this->jabatan->id,
                'unit_id' => $this->unit->id,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_akun_pengguna_tidak_dapat_menyunting_pengguna_lain(): void
    {
        $this->actingAs($this->anggota)
            ->patch("/admin/users/{$this->anggota->id}", $this->formulir())
            ->assertForbidden();
    }

    // -- Nonaktifkan dan aktifkan kembali -------------------------------------

    public function test_superadmin_dapat_menonaktifkan_pengguna_lain(): void
    {
        $this->actingAs($this->superadmin)
            ->delete("/admin/users/{$this->anggota->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->anggota->fresh()->is_active);
    }

    public function test_superadmin_tidak_dapat_menonaktifkan_dirinya_sendiri(): void
    {
        $this->actingAs($this->superadmin)
            ->delete("/admin/users/{$this->superadmin->id}")
            ->assertSessionHasErrors();

        $this->assertTrue($this->superadmin->fresh()->is_active);
    }

    public function test_superadmin_dapat_mengaktifkan_kembali_pengguna(): void
    {
        $this->anggota->update(['is_active' => false]);

        $this->actingAs($this->superadmin)
            ->patch("/admin/users/{$this->anggota->id}/restore")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->anggota->fresh()->is_active);
    }

    public function test_akun_yang_dinonaktifkan_langsung_kehilangan_sesi(): void
    {
        // Bukan cuma ditolak masuk lagi — sesi yang SEDANG berjalan wajib
        // diputus di permintaan berikutnya (FR-27, sama seperti dokumen).
        $this->actingAs($this->anggota)->get('/dashboard')->assertOk();

        $this->anggota->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_akun_pengguna_tidak_dapat_menonaktifkan_siapa_pun(): void
    {
        $lainnya = User::factory()->create([
            'jabatan_id' => $this->jabatan->id,
            'unit_id' => $this->unit->id,
        ]);

        $this->actingAs($this->anggota)
            ->delete("/admin/users/{$lainnya->id}")
            ->assertForbidden();
    }

    // -- Atur ulang kata sandi -------------------------------------------------

    public function test_superadmin_dapat_mengatur_ulang_kata_sandi(): void
    {
        $this->actingAs($this->superadmin)
            ->patch("/admin/users/{$this->anggota->id}/password", [
                'password' => 'kata-sandi-baru-123',
                'password_confirmation' => 'kata-sandi-baru-123',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->post('/logout');

        $this->post('/login', [
            'email' => $this->anggota->email,
            'password' => 'kata-sandi-baru-123',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_kata_sandi_baru_wajib_dikonfirmasi(): void
    {
        $this->actingAs($this->superadmin)
            ->patch("/admin/users/{$this->anggota->id}/password", [
                'password' => 'kata-sandi-baru-123',
                'password_confirmation' => 'tidak-cocok',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_akun_pengguna_tidak_dapat_mengatur_ulang_kata_sandi_siapa_pun(): void
    {
        $this->actingAs($this->anggota)
            ->patch("/admin/users/{$this->anggota->id}/password", [
                'password' => 'kata-sandi-baru-123',
                'password_confirmation' => 'kata-sandi-baru-123',
            ])
            ->assertForbidden();
    }

    public function test_jabatan_dan_unit_nonaktif_tidak_muncul_di_pilihan_formulir(): void
    {
        $jabatanNonaktif = Jabatan::factory()->tingkat(3)->create(['is_active' => false, 'nama' => 'Jabatan Usang']);
        $unitNonaktif = Unit::factory()->create(['is_active' => false, 'nama' => 'Unit Usang']);

        $this->actingAs($this->superadmin)->get('/admin/users/create')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Users/Create')
                ->where(
                    'opsi.jabatan',
                    fn ($daftar) => collect($daftar)->doesntContain(fn ($j) => $j['id'] === $jabatanNonaktif->id),
                )
                ->where(
                    'opsi.unit',
                    fn ($daftar) => collect($daftar)->doesntContain(fn ($u) => $u['id'] === $unitNonaktif->id),
                ));
    }
}
