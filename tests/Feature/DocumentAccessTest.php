<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentEditScope;
use App\Models\Category;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Matriks hak melihat dokumen — bagian paling penting di seluruh proyek.
 *
 * Kerusakan di sini berarti kebocoran data, dan tidak akan terlihat dari
 * tampilan: halaman tetap terbuka, daftarnya tetap terisi, hanya saja memuat
 * dokumen yang seharusnya tertutup. Karena itu setiap jalur mekanisme diuji
 * terpisah, dan selalu disertai kontrol negatif — tanpa itu, sistem akses yang
 * selalu menjawab "boleh" akan terlihat lolos seluruh pengujian.
 */
final class DocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    private Unit $deputi;

    private Unit $divisiA;

    private Unit $divisiB;

    private User $anggotaA;

    private User $anggotaB;

    private User $deputiUser;

    private User $pimpinan;

    private User $superadmin;

    private User $pengunggah;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([User::ROLE_SUPERADMIN, User::ROLE_PENGGUNA] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $tingkat1 = Jabatan::factory()->tingkat(1)->create(['nama' => 'Kepala']);
        $tingkat2 = Jabatan::factory()->tingkat(2)->create(['nama' => 'Deputi']);
        $tingkat4 = Jabatan::factory()->tingkat(4)->create(['nama' => 'Anggota']);

        $this->deputi = Unit::factory()->tingkatAtas()->create(['nama' => 'Deputi Uji']);
        $this->divisiA = Unit::factory()->dibawah($this->deputi)->create(['nama' => 'Divisi A']);
        $this->divisiB = Unit::factory()->dibawah($this->deputi)->create(['nama' => 'Divisi B']);

        $this->anggotaA = $this->buatPengguna($tingkat4->id, $this->divisiA->id);
        $this->anggotaB = $this->buatPengguna($tingkat4->id, $this->divisiB->id);
        $this->deputiUser = $this->buatPengguna($tingkat2->id, $this->deputi->id);
        $this->pimpinan = $this->buatPengguna($tingkat1->id, null);
        $this->pengunggah = $this->buatPengguna($tingkat4->id, $this->divisiB->id);

        $this->superadmin = User::factory()->create(['jabatan_id' => null, 'unit_id' => null]);
        $this->superadmin->assignRole(User::ROLE_SUPERADMIN);
    }

    private function buatPengguna(int $jabatanId, ?int $unitId): User
    {
        $user = User::factory()->create(['jabatan_id' => $jabatanId, 'unit_id' => $unitId]);
        $user->assignRole(User::ROLE_PENGGUNA);

        return $user->load(['jabatan', 'roles']);
    }

    private function buatDokumen(array $atribut = []): Document
    {
        return Document::factory()->create([
            'category_id' => Category::factory(),
            'origin_unit_id' => $this->divisiA->id,
            'uploaded_by' => $this->pengunggah->id,
            ...$atribut,
        ]);
    }

    private function terlihatOleh(Document $document, User $user): bool
    {
        return Document::query()->visibleTo($user)->whereKey($document->getKey())->exists();
    }

    // -- Mekanisme 1: bagikan ke semua ---------------------------------------

    public function test_dokumen_yang_dibagikan_ke_semua_terlihat_siapa_pun(): void
    {
        $document = $this->buatDokumen(['is_shared_to_all' => true]);

        $this->assertTrue($this->terlihatOleh($document, $this->anggotaA));
        $this->assertTrue($this->terlihatOleh($document, $this->anggotaB));
        $this->assertTrue($this->terlihatOleh($document, $this->deputiUser));
    }

    // -- Mekanisme 2: jenjang jabatan ----------------------------------------

    public function test_jenjang_jabatan_membuka_akses_lintas_unit(): void
    {
        // Ambang 2: terlihat oleh tingkat 1 dan 2, tertutup bagi tingkat 4 —
        // berapa pun unit mereka.
        $document = $this->buatDokumen(['min_tingkat_akses' => 2]);

        $this->assertTrue($this->terlihatOleh($document, $this->deputiUser));
        $this->assertFalse($this->terlihatOleh($document, $this->anggotaA));
        $this->assertFalse($this->terlihatOleh($document, $this->anggotaB));
    }

    // -- Mekanisme 3: unit kerja ---------------------------------------------

    public function test_unit_membuka_akses_hanya_bagi_anggotanya(): void
    {
        $document = $this->buatDokumen();
        $document->targetUnits()->attach($this->divisiA->id);

        $this->assertTrue($this->terlihatOleh($document, $this->anggotaA));
        $this->assertFalse($this->terlihatOleh($document, $this->anggotaB));
    }

    public function test_unit_induk_tidak_ikut_terbuka_saat_hanya_divisi_yang_dipilih(): void
    {
        // Inti keputusan `Catatan_Audit.md` isu #15. Cascade diselesaikan saat
        // menyimpan, bukan dihitung ulang saat membaca — sehingga pengunggah
        // yang sengaja hanya memilih satu divisi benar-benar dihormati, dan
        // anggota unit induk TIDAK ikut mendapat akses.
        $document = $this->buatDokumen();
        $document->targetUnits()->attach($this->divisiA->id);

        $this->assertFalse(
            $this->terlihatOleh($document, $this->deputiUser),
            'Anggota unit induk tidak boleh ikut terbuka ketika hanya divisi yang dipilih.',
        );
    }

    public function test_cascade_yang_tersimpan_membuka_akses_untuk_seluruh_divisi(): void
    {
        // Ketika pengunggah memang memilih unit induk, resolver menyimpan
        // seluruh divisi sebagai baris tersendiri — dan semuanya terbuka.
        $document = $this->buatDokumen();
        $document->targetUnits()->attach([
            $this->deputi->id, $this->divisiA->id, $this->divisiB->id,
        ]);

        $this->assertTrue($this->terlihatOleh($document, $this->deputiUser));
        $this->assertTrue($this->terlihatOleh($document, $this->anggotaA));
        $this->assertTrue($this->terlihatOleh($document, $this->anggotaB));
    }

    // -- Mekanisme 4: orang tertentu -----------------------------------------

    public function test_orang_tertentu_membuka_akses_lintas_unit_dan_jabatan(): void
    {
        $document = $this->buatDokumen();
        $document->sharedUsers()->attach($this->anggotaB->id);

        $this->assertTrue($this->terlihatOleh($document, $this->anggotaB));
        $this->assertFalse($this->terlihatOleh($document, $this->anggotaA));
    }

    // -- Kombinasi ------------------------------------------------------------

    public function test_tiga_mekanisme_aktif_bersamaan_masing_masing_berdiri_sendiri(): void
    {
        // Skenario demo `PRD.md` §6: satu dokumen, tiga jalur akses berbeda,
        // dan satu akun yang tidak memenuhi satu pun.
        $document = $this->buatDokumen(['min_tingkat_akses' => 2]);
        $document->targetUnits()->attach($this->divisiA->id);
        $document->sharedUsers()->attach($this->anggotaB->id);

        $this->assertTrue($this->terlihatOleh($document, $this->anggotaA), 'lewat unit');
        $this->assertTrue($this->terlihatOleh($document, $this->deputiUser), 'lewat jenjang jabatan');
        $this->assertTrue($this->terlihatOleh($document, $this->anggotaB), 'lewat orang tertentu');

        $luar = $this->buatPengguna(
            Jabatan::factory()->tingkat(4)->create()->id,
            Unit::factory()->create()->id,
        );

        $this->assertFalse($this->terlihatOleh($document, $luar), 'kontrol negatif');
    }

    // -- Jaminan bawaan -------------------------------------------------------

    public function test_pengunggah_selalu_melihat_dokumennya_sendiri(): void
    {
        // Tanpa jaminan ini, pengunggah yang mengarahkan akses ke pihak lain
        // akan kehilangan dokumen buatannya sendiri (`PRD.md` §2.6).
        $document = $this->buatDokumen();
        $document->targetUnits()->attach($this->divisiA->id);

        $this->assertFalse(
            $this->pengunggah->unit_id === $this->divisiA->id,
            'Prasyarat: pengunggah harus berada di luar unit sasaran.',
        );
        $this->assertTrue($this->terlihatOleh($document, $this->pengunggah));
    }

    public function test_dokumen_tanpa_mekanisme_hanya_terlihat_pengunggah_dan_pimpinan(): void
    {
        $document = $this->buatDokumen();

        $this->assertTrue($this->terlihatOleh($document, $this->pengunggah));
        $this->assertTrue($this->terlihatOleh($document, $this->pimpinan));
        $this->assertTrue($this->terlihatOleh($document, $this->superadmin));

        $this->assertFalse($this->terlihatOleh($document, $this->anggotaA));
        $this->assertFalse($this->terlihatOleh($document, $this->deputiUser));
    }

    // -- Pelewatan ------------------------------------------------------------

    public function test_jabatan_tingkat_satu_melihat_seluruh_dokumen(): void
    {
        $this->buatDokumen();
        $this->buatDokumen(['min_tingkat_akses' => 4]);
        $this->buatDokumen(['is_shared_to_all' => false]);

        $this->assertSame(3, Document::query()->visibleTo($this->pimpinan)->count());
    }

    public function test_superadmin_melihat_seluruh_dokumen_meski_tanpa_jabatan(): void
    {
        $this->buatDokumen();
        $this->buatDokumen();

        $this->assertNull($this->superadmin->jabatan_id);
        $this->assertSame(2, Document::query()->visibleTo($this->superadmin)->count());
    }

    public function test_pengguna_tanpa_jabatan_tidak_menghentikan_aplikasi(): void
    {
        // Superadmin memang tanpa jabatan, tapi pengguna biasa yang jabatannya
        // belum diisi juga tidak boleh membuat evaluasi akses gagal
        // (`Catatan_Audit.md` isu #16).
        $tanpaJabatan = User::factory()->create(['jabatan_id' => null, 'unit_id' => null]);
        $tanpaJabatan->assignRole(User::ROLE_PENGGUNA);

        $this->buatDokumen(['is_shared_to_all' => true]);
        $this->buatDokumen(['min_tingkat_akses' => 2]);

        $this->assertSame(1, Document::query()->visibleTo($tanpaJabatan)->count());
    }

    // -- Penyaring tambahan ---------------------------------------------------

    public function test_penyaring_tambahan_tidak_membocorkan_dokumen(): void
    {
        // Rantai OR wajib terbungkus satu grup. Tanpa pembungkus, penyaring
        // yang ditambahkan controller sesudahnya akan tercampur ke dalam rantai
        // OR dan meloloskan dokumen yang seharusnya tertutup.
        $terlihat = $this->buatDokumen(['is_shared_to_all' => true, 'judul' => 'Laporan Terbuka']);
        $tertutup = $this->buatDokumen(['judul' => 'Laporan Tertutup']);

        $hasil = Document::query()
            ->visibleTo($this->anggotaA)
            ->where('judul', 'like', 'Laporan%')
            ->pluck('id');

        $this->assertTrue($hasil->contains($terlihat->id));
        $this->assertFalse($hasil->contains($tertutup->id));
    }

    public function test_penyaringan_dikerjakan_dalam_satu_query(): void
    {
        $this->buatDokumen(['is_shared_to_all' => true]);

        // Relasi dimuat lebih dulu supaya yang terhitung hanya query dokumen,
        // bukan pencarian jabatan atau role.
        $user = $this->anggotaA->load(['jabatan', 'roles']);

        $jumlah = 0;
        DB::listen(function () use (&$jumlah): void {
            $jumlah++;
        });

        Document::query()->visibleTo($user)->get();

        $this->assertSame(
            1,
            $jumlah,
            'Penyaringan hak akses harus terjadi di satu query SQL, bukan disaring di PHP.',
        );
    }

    // -- Policy ---------------------------------------------------------------

    public function test_policy_menolak_melihat_dokumen_di_luar_hak(): void
    {
        $document = $this->buatDokumen();

        $this->assertTrue($this->anggotaA->cannot('view', $document));
        $this->assertTrue($this->pengunggah->can('view', $document));
    }

    public function test_edit_scope_hanya_pengunggah_menolak_pihak_lain(): void
    {
        $document = $this->buatDokumen([
            'is_shared_to_all' => true,
            'edit_scope' => DocumentEditScope::OwnerOnly,
        ]);

        // Terlihat semua orang, tapi hanya pengunggahnya yang boleh menyunting.
        $this->assertTrue($this->anggotaA->can('view', $document));
        $this->assertTrue($this->anggotaA->cannot('update', $document));
        $this->assertTrue($this->pengunggah->can('update', $document));
    }

    public function test_edit_scope_sama_seperti_akses_mengikuti_hak_melihat(): void
    {
        $document = $this->buatDokumen([
            'edit_scope' => DocumentEditScope::MatchVisibility,
        ]);
        $document->targetUnits()->attach($this->divisiA->id);

        $this->assertTrue($this->anggotaA->can('update', $document));
        $this->assertTrue($this->anggotaB->cannot('update', $document));
    }

    public function test_pimpinan_dan_superadmin_dapat_menyunting_dokumen_siapa_pun(): void
    {
        $document = $this->buatDokumen(['edit_scope' => DocumentEditScope::OwnerOnly]);

        $this->assertTrue($this->pimpinan->can('update', $document));
        $this->assertTrue($this->superadmin->can('update', $document));
    }

    public function test_wewenang_menonaktifkan_sama_dengan_menyunting(): void
    {
        $document = $this->buatDokumen(['edit_scope' => DocumentEditScope::OwnerOnly]);

        $this->assertTrue($this->pengunggah->can('delete', $document));
        $this->assertTrue($this->anggotaA->cannot('delete', $document));
    }

    public function test_hanya_superadmin_yang_dapat_mengaktifkan_kembali(): void
    {
        $document = $this->buatDokumen(['is_active' => false]);

        $this->assertTrue($this->superadmin->can('restore', $document));
        $this->assertTrue($this->pengunggah->cannot('restore', $document));
        $this->assertTrue($this->pimpinan->cannot('restore', $document));
    }

    // -- Ringkasan akses ------------------------------------------------------

    public function test_ringkasan_akses_mencerminkan_mekanisme_yang_benar_benar_aktif(): void
    {
        $document = $this->buatDokumen(['min_tingkat_akses' => 2]);
        $document->targetUnits()->attach($this->divisiA->id);
        $document->sharedUsers()->attach($this->anggotaB->id);
        $document->load(['targetUnits', 'sharedUsers']);

        $ringkasan = $document->accessSummary();

        $this->assertCount(3, $ringkasan);
        $this->assertSame(3, $document->jumlahMekanismeAktif());
        $this->assertStringContainsString('Divisi A', $ringkasan[0]);
        $this->assertStringContainsString('tingkat 2', $ringkasan[1]);
        $this->assertStringContainsString('1 orang', $ringkasan[2]);
    }

    public function test_ringkasan_akses_menyatakan_dokumen_tanpa_mekanisme_apa_adanya(): void
    {
        $document = $this->buatDokumen()->load(['targetUnits', 'sharedUsers']);

        $this->assertSame(['Hanya pengunggah'], $document->accessSummary());
        $this->assertSame(0, $document->jumlahMekanismeAktif());
    }

    public function test_bagikan_ke_semua_meniadakan_rincian_mekanisme_lain(): void
    {
        // Kombinasi semacam ini sah tapi redundan: semua orang sudah dapat
        // melihat lewat mekanisme "semua" (`Catatan_Audit.md` catatan tambahan).
        $document = $this->buatDokumen(['is_shared_to_all' => true, 'min_tingkat_akses' => 2]);
        $document->load(['targetUnits', 'sharedUsers']);

        $this->assertSame(['Semua pengguna'], $document->accessSummary());
    }
}
