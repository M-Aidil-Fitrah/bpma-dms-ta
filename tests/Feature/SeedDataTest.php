<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\JabatanSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuperadminSeeder;
use Database\Seeders\UnitSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Menjaga bentuk seed data tetap seperti yang dijanjikan spesifikasi.
 *
 * Bukan sekadar mencocokkan angka: jumlah dan sebaran akun inilah yang membuat
 * matriks pengujian otorisasi (FEAT-05) punya bahan uji yang cukup. Kalau seed
 * bergeser diam-diam, tes otorisasi bisa lolos karena datanya kebetulan aman,
 * bukan karena kodenya benar.
 */
final class SeedDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('dms.superadmin.email', 'superadmin@bpma.internal');
        config()->set('dms.superadmin.password', 'kata-sandi-uji');

        // Hanya seeder data referensi dan akun yang dijalankan — bukan
        // `DatabaseSeeder` lengkap. Seeder dokumen menyalin 220 berkas ke
        // cakram dan memakan belasan detik; menjalankannya ulang di setiap
        // metode akan membuat berkas tes ini sendirian melampaui anggaran
        // waktu seluruh rangkaian. Sebarannya diuji terpisah di
        // `DocumentSeedTest`.
        $this->seed([
            RoleSeeder::class,
            JabatanSeeder::class,
            UnitSeeder::class,
            CategorySeeder::class,
            SuperadminSeeder::class,
            UserSeeder::class,
        ]);
    }

    public function test_data_referensi_ter_seed_lengkap_dan_aktif(): void
    {
        $this->assertSame(5, Jabatan::count());
        $this->assertSame(20, Unit::count());
        $this->assertSame(11, Category::count());

        $this->assertSame(0, Jabatan::where('is_active', false)->count());
        $this->assertSame(0, Unit::where('is_active', false)->count());
        $this->assertSame(0, Category::where('is_active', false)->count());
    }

    public function test_struktur_unit_membentuk_pohon_dua_tingkat(): void
    {
        $this->assertSame(5, Unit::whereNull('parent_id')->count());
        $this->assertSame(15, Unit::whereNotNull('parent_id')->count());
    }

    public function test_hanya_ada_dua_role_sistem(): void
    {
        // Kriteria Penerimaan #3 — role tidak pernah bertambah seiring
        // bertambahnya jabatan.
        $this->assertSame(2, Role::count());
    }

    public function test_jumlah_dan_sebaran_akun_sesuai_spesifikasi(): void
    {
        $this->assertSame(46, User::count());
        $this->assertSame(1, User::role(User::ROLE_SUPERADMIN)->count());
        $this->assertSame(45, User::role(User::ROLE_PENGGUNA)->count());
        $this->assertSame(3, User::where('is_active', false)->count());
    }

    public function test_hanya_superadmin_yang_tanpa_jabatan(): void
    {
        // Pengguna tanpa jabatan akan menghentikan evaluasi akses, karena
        // tingkat jabatannya tidak dapat dibaca (`Catatan_Audit.md` isu #16).
        $tanpaJabatan = User::whereNull('jabatan_id')->get();

        $this->assertCount(1, $tanpaJabatan);
        $this->assertTrue($tanpaJabatan->first()->isSuperadmin());
    }

    public function test_hanya_jabatan_tingkat_satu_yang_boleh_tanpa_unit(): void
    {
        $tanpaUnit = User::with('jabatan')
            ->whereNull('unit_id')
            ->whereNotNull('jabatan_id')
            ->get();

        $this->assertCount(2, $tanpaUnit);

        foreach ($tanpaUnit as $user) {
            $this->assertSame(1, $user->jabatan->tingkat_akses);
        }
    }

    /**
     * @return list<array{string, string, int, string}>
     */
    public static function akunSkenarioDemo(): array
    {
        return [
            ['fitri.handayani@bpma.internal', 'Anggota', 4, 'Divisi Manajemen Sistem Teknologi Informasi'],
            ['hasan.basri@bpma.internal', 'Deputi', 2, 'Deputi Dukungan Bisnis'],
            ['maya.puspita@bpma.internal', 'Anggota', 4, 'Divisi Keuangan Internal'],
            ['rizki.ananda@bpma.internal', 'Anggota', 4, 'Divisi Operasi Produksi'],
        ];
    }

    /**
     * Keempat akun ini dipakai membuktikan tiap jalur mekanisme akses di
     * FEAT-05. Jabatan dan unitnya tidak boleh bergeser.
     */
    #[DataProvider('akunSkenarioDemo')]
    public function test_akun_skenario_demo_tersedia_dengan_atribut_tepat(
        string $email,
        string $jabatan,
        int $tingkat,
        string $unit,
    ): void {
        $user = User::with(['jabatan', 'unit'])->where('email', $email)->first();

        $this->assertNotNull($user, "Akun skenario demo {$email} tidak ditemukan.");
        $this->assertTrue($user->is_active);
        $this->assertSame($jabatan, $user->jabatan->nama);
        $this->assertSame($tingkat, $user->jabatan->tingkat_akses);
        $this->assertSame($unit, $user->unit->nama);
    }

    public function test_seeding_berulang_tidak_menggandakan_data(): void
    {
        $this->seed([RoleSeeder::class, JabatanSeeder::class, UnitSeeder::class,
            CategorySeeder::class, SuperadminSeeder::class, UserSeeder::class]);

        $this->assertSame(46, User::count());
        $this->assertSame(20, Unit::count());
        $this->assertSame(2, Role::count());
    }
}
