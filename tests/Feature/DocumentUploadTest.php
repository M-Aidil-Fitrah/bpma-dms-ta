<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentEditScope;
use App\Enums\ExtractionStatus;
use App\Jobs\ExtractDocumentTextJob;
use App\Jobs\GenerateDocumentThumbnailJob;
use App\Models\Category;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\Unit;
use App\Models\User;
use App\Services\PengaturanService;
use App\Support\JenjangAkses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Unggah dokumen (FR-06, FR-12, FR-37 s.d. FR-42).
 *
 * Menekankan keadaan terburuk, bukan jalur bahagia: berkas berbahaya, kombinasi
 * akses yang kosong, entitas yang dinonaktifkan setelah halaman terbuka, dan
 * kegagalan di tengah penyimpanan yang menyisakan berkas tanpa pemilik.
 */
final class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $pengunggah;

    private Unit $deputi;

    private Unit $divisi;

    private Category $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(User::ROLE_PENGGUNA, 'web');

        $jabatan = Jabatan::factory()->tingkat(4)->create();
        $this->deputi = Unit::factory()->tingkatAtas()->create();
        $this->divisi = Unit::factory()->dibawah($this->deputi)->create();
        $this->kategori = Category::factory()->create();

        $this->pengunggah = User::factory()->create([
            'jabatan_id' => $jabatan->id,
            'unit_id' => $this->divisi->id,
        ]);
        $this->pengunggah->assignRole(User::ROLE_PENGGUNA);

        // `QUEUE_CONNECTION=sync` di tes membuat job berjalan sinkron di
        // dalam request. Berkas PDF di tes ini palsu (0 byte atau teks
        // biasa), jadi kalau job benar-benar jalan ia akan ditandai
        // `failed` dan mematahkan tes yang tidak sedang menguji ekstraksi
        // sama sekali.
        Queue::fake();
    }

    /**
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function formulir(array $ubah = []): array
    {
        return [
            'nomor' => '001/BPMA/UJI/VIII/2026',
            'judul' => 'Dokumen Uji Unggah',
            'category_id' => $this->kategori->id,
            'origin_unit_id' => $this->divisi->id,
            'tanggal' => '2026-08-15',
            'edit_scope' => DocumentEditScope::OwnerOnly->value,
            'file' => UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf'),
            'is_shared_to_all' => true,
            ...$ubah,
        ];
    }

    // -- Jalur normal ---------------------------------------------------------

    public function test_dokumen_tersimpan_beserta_berkasnya(): void
    {
        $this->actingAs($this->pengunggah)
            ->post('/documents', $this->formulir())
            ->assertRedirect();

        $document = Document::firstWhere('judul', 'Dokumen Uji Unggah');

        $this->assertNotNull($document);
        $this->assertSame($this->pengunggah->id, $document->uploaded_by);
        $this->assertTrue(Storage::disk('local')->exists($document->file_path));
    }

    public function test_pola_jalur_berkas_sama_dengan_yang_dipakai_seeder(): void
    {
        // Kalau pola ini berbeda dari `BerkasContoh::salin()`, pratinjau akan
        // bekerja untuk data seed tapi gagal untuk unggahan sungguhan — dan
        // perbedaannya baru ketahuan saat demo.
        $this->actingAs($this->pengunggah)->post('/documents', $this->formulir());

        $this->assertMatchesRegularExpression(
            '#^documents/\d{4}/\d{2}/[0-9a-f-]{36}\.pdf$#',
            Document::firstWhere('judul', 'Dokumen Uji Unggah')->file_path,
        );
    }

    public function test_nama_berkas_asli_tidak_pernah_menjadi_jalur_di_disk(): void
    {
        // Nama berkas sepenuhnya dikendalikan klien. Memakainya sebagai jalur
        // membuka jalan penjelajahan direktori.
        $this->actingAs($this->pengunggah)->post('/documents', $this->formulir([
            'file' => UploadedFile::fake()->create('../../etc/passwd.pdf', 10, 'application/pdf'),
        ]));

        $document = Document::firstWhere('judul', 'Dokumen Uji Unggah');

        $this->assertStringNotContainsString('..', $document->file_path);
        $this->assertStringStartsWith('documents/', $document->file_path);
    }

    // -- Mekanisme akses ------------------------------------------------------

    public function test_dokumen_tanpa_mekanisme_akses_ditolak(): void
    {
        // Kriteria Penerimaan #9.
        $this->actingAs($this->pengunggah)
            ->post('/documents', $this->formulir(['is_shared_to_all' => false]))
            ->assertSessionHasErrors('akses');

        $this->assertSame(0, Document::count());
    }

    public function test_berkas_tidak_tertinggal_saat_validasi_menolak(): void
    {
        $this->actingAs($this->pengunggah)
            ->post('/documents', $this->formulir(['is_shared_to_all' => false]));

        // Validasi berjalan sebelum berkas disimpan, jadi tidak boleh ada apa
        // pun yang tertulis ke penyimpanan.
        $this->assertEmpty(Storage::disk('local')->allFiles('documents'));
    }

    public function test_unit_tersimpan_persis_seperti_yang_dikirim(): void
    {
        // Cascade "pilih Deputi berarti seluruh divisinya ikut" (FR-39)
        // diselesaikan di antarmuka: `UnitTreePicker` mencentang induk beserta
        // anaknya, lalu mengirim daftar lengkapnya. Server menyimpan apa adanya
        // dan tidak menambahkan apa pun sendiri.
        $divisiLain = Unit::factory()->dibawah($this->deputi)->create();

        $this->actingAs($this->pengunggah)->post('/documents', $this->formulir([
            'is_shared_to_all' => false,
            'unit_ids' => [$this->deputi->id, $this->divisi->id, $divisiLain->id],
        ]));

        $terlampir = Document::firstWhere('judul', 'Dokumen Uji Unggah')
            ->targetUnits->pluck('id');

        $this->assertCount(3, $terlampir);
        $this->assertTrue($terlampir->contains($this->divisi->id));
        $this->assertTrue($terlampir->contains($divisiLain->id));
    }

    public function test_divisi_yang_sengaja_dibuang_tidak_dipasang_kembali(): void
    {
        // Inti FR-39. Server sempat menurunkan pohon unit sendiri saat
        // menyimpan, sehingga selama induknya masih tercentang, divisi yang
        // baru saja dibuang pengguna dipasang lagi tanpa satu pun pesan —
        // pengaturan manualnya diabaikan diam-diam.
        $dibuang = Unit::factory()->dibawah($this->deputi)->create();

        $this->actingAs($this->pengunggah)->post('/documents', $this->formulir([
            'is_shared_to_all' => false,
            'unit_ids' => [$this->deputi->id, $this->divisi->id],
        ]));

        $terlampir = Document::firstWhere('judul', 'Dokumen Uji Unggah')
            ->targetUnits->pluck('id');

        $this->assertFalse(
            $terlampir->contains($dibuang->id),
            'Divisi yang dibuang pengguna dipasang kembali oleh server.',
        );
        $this->assertCount(2, $terlampir);
    }

    public function test_tiga_mekanisme_dapat_aktif_bersamaan(): void
    {
        $penerima = User::factory()->create([
            'jabatan_id' => $this->pengunggah->jabatan_id,
            'unit_id' => Unit::factory()->create()->id,
        ]);

        $this->actingAs($this->pengunggah)->post('/documents', $this->formulir([
            'is_shared_to_all' => false,
            'min_tingkat_akses' => 4,
            'unit_ids' => [$this->divisi->id],
            'shared_user_ids' => [$penerima->id],
        ]));

        $document = Document::firstWhere('judul', 'Dokumen Uji Unggah');

        $this->assertSame(4, $document->min_tingkat_akses);
        $this->assertCount(1, $document->targetUnits);
        $this->assertCount(1, $document->sharedUsers);
    }

    public function test_jejak_pemberi_akses_ikut_tercatat(): void
    {
        $penerima = User::factory()->create([
            'jabatan_id' => $this->pengunggah->jabatan_id,
            'unit_id' => $this->divisi->id,
        ]);

        $this->actingAs($this->pengunggah)->post('/documents', $this->formulir([
            'is_shared_to_all' => false,
            'unit_ids' => [$this->divisi->id],
            'shared_user_ids' => [$penerima->id],
        ]));

        $document = Document::firstWhere('judul', 'Dokumen Uji Unggah');

        $this->assertSame(
            $this->pengunggah->id,
            $document->targetUnits->first()->pivot->added_by,
        );
        $this->assertSame(
            $this->pengunggah->id,
            $document->sharedUsers->first()->pivot->granted_by,
        );
    }

    // -- Entitas yang dinonaktifkan setelah halaman terbuka -------------------

    public function test_kategori_nonaktif_ditolak(): void
    {
        $nonaktif = Category::factory()->nonaktif()->create();

        $this->actingAs($this->pengunggah)
            ->post('/documents', $this->formulir(['category_id' => $nonaktif->id]))
            ->assertSessionHasErrors('category_id');
    }

    public function test_unit_nonaktif_ditolak(): void
    {
        $nonaktif = Unit::factory()->nonaktif()->create();

        $this->actingAs($this->pengunggah)
            ->post('/documents', $this->formulir([
                'is_shared_to_all' => false,
                'unit_ids' => [$nonaktif->id],
            ]))
            ->assertSessionHasErrors('unit_ids.0');
    }

    public function test_pengguna_nonaktif_tidak_dapat_diberi_akses(): void
    {
        $nonaktif = User::factory()->create([
            'jabatan_id' => $this->pengunggah->jabatan_id,
            'unit_id' => $this->divisi->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->pengunggah)
            ->post('/documents', $this->formulir([
                'is_shared_to_all' => false,
                'shared_user_ids' => [$nonaktif->id],
            ]))
            ->assertSessionHasErrors('shared_user_ids.0');
    }

    // -- Keamanan berkas ------------------------------------------------------

    /**
     * @return list<array{string}>
     */
    public static function ekstensiBerbahaya(): array
    {
        return [['exe'], ['sh'], ['php'], ['bat'], ['jar']];
    }

    #[DataProvider('ekstensiBerbahaya')]
    public function test_ekstensi_berbahaya_ditolak(string $ekstensi): void
    {
        $this->actingAs($this->pengunggah)
            ->post('/documents', $this->formulir([
                'file' => UploadedFile::fake()->create("virus.{$ekstensi}", 10),
            ]))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_ekstensi_berbahaya_ditolak_meski_mime_disamarkan(): void
    {
        // Tipe MIME sepenuhnya dikendalikan klien. Pemeriksaan ekstensi tidak
        // boleh bergantung padanya.
        $this->actingAs($this->pengunggah)
            ->post('/documents', $this->formulir([
                'file' => UploadedFile::fake()->create('jahat.php', 10, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('file');
    }

    // -- Status ekstraksi -----------------------------------------------------

    public function test_tipe_didukung_ditandai_menunggu_ekstraksi(): void
    {
        $this->actingAs($this->pengunggah)->post('/documents', $this->formulir());

        $this->assertSame(
            ExtractionStatus::Pending,
            Document::firstWhere('judul', 'Dokumen Uji Unggah')->extraction_status,
        );
    }

    public function test_tipe_tak_didukung_ditandai_tidak_berlaku(): void
    {
        // Kriteria Penerimaan #14 — tidak ada job yang perlu dibuat.
        $this->actingAs($this->pengunggah)->post('/documents', $this->formulir([
            'file' => UploadedFile::fake()->create('rekaman.mp4', 50, 'video/mp4'),
        ]));

        $this->assertSame(
            ExtractionStatus::NotApplicable,
            Document::firstWhere('judul', 'Dokumen Uji Unggah')->extraction_status,
        );
    }

    public function test_tipe_didukung_memicu_job_ekstraksi(): void
    {
        $this->actingAs($this->pengunggah)->post('/documents', $this->formulir());

        $document = Document::firstWhere('judul', 'Dokumen Uji Unggah');

        Queue::assertPushed(
            ExtractDocumentTextJob::class,
            fn (ExtractDocumentTextJob $job): bool => $job->document->is($document),
        );
    }

    public function test_pdf_memicu_job_gambar_mini_terpisah(): void
    {
        $this->actingAs($this->pengunggah)->post('/documents', $this->formulir());

        $document = Document::firstWhere('judul', 'Dokumen Uji Unggah');

        Queue::assertPushed(
            GenerateDocumentThumbnailJob::class,
            fn (GenerateDocumentThumbnailJob $job): bool => $job->document->is($document),
        );
    }

    public function test_tipe_tak_didukung_tidak_memicu_job(): void
    {
        // Kriteria Penerimaan #14 — tidak ada job yang perlu dibuat sama
        // sekali, bukan job yang dibuat lalu langsung selesai tanpa kerja.
        $this->actingAs($this->pengunggah)->post('/documents', $this->formulir([
            'file' => UploadedFile::fake()->create('rekaman.mp4', 50, 'video/mp4'),
        ]));

        Queue::assertNotPushed(ExtractDocumentTextJob::class);
    }

    public function test_heic_ditandai_tidak_berlaku_bukan_gagal(): void
    {
        // FR-32d — foto kamera iPhone bawaan. Bukan kegagalan; berkasnya tetap
        // tersimpan normal, hanya isinya yang tidak dapat dicari.
        $this->actingAs($this->pengunggah)->post('/documents', $this->formulir([
            'file' => UploadedFile::fake()->create('foto.heic', 200, 'image/heic'),
        ]));

        $this->assertSame(
            ExtractionStatus::NotApplicable,
            Document::firstWhere('judul', 'Dokumen Uji Unggah')->extraction_status,
        );
    }

    public function test_gambar_ditandai_menunggu_dan_memicu_job_ocr(): void
    {
        $this->actingAs($this->pengunggah)->post('/documents', $this->formulir([
            'file' => UploadedFile::fake()->create('foto.jpg', 200, 'image/jpeg'),
        ]));

        $document = Document::firstWhere('judul', 'Dokumen Uji Unggah');

        $this->assertSame(ExtractionStatus::Pending, $document->extraction_status);
        Queue::assertPushed(
            ExtractDocumentTextJob::class,
            fn (ExtractDocumentTextJob $job): bool => $job->document->is($document),
        );
    }

    // -- Otorisasi ------------------------------------------------------------

    public function test_tamu_tidak_dapat_mengunggah(): void
    {
        $this->post('/documents', $this->formulir())->assertRedirect(route('login'));
        $this->assertSame(0, Document::count());
    }

    public function test_akun_nonaktif_tidak_dapat_mengunggah(): void
    {
        $nonaktif = User::factory()->create([
            'jabatan_id' => $this->pengunggah->jabatan_id,
            'unit_id' => $this->divisi->id,
            'is_active' => false,
        ]);
        $nonaktif->assignRole(User::ROLE_PENGGUNA);

        // Dihentikan lebih awal daripada sekadar 403: middleware memutus
        // sesinya, jadi permintaannya bahkan tidak mencapai Policy. Yang
        // dijamin di sini adalah akibatnya — tidak ada dokumen yang tersimpan
        // dan tidak ada berkas yang tertulis.
        $this->actingAs($nonaktif)
            ->post('/documents', $this->formulir())
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, Document::count());
        $this->assertEmpty(Storage::disk('local')->allFiles('documents'));
    }

    // -- Kegagalan di tengah jalan --------------------------------------------

    public function test_berkas_tidak_tertinggal_saat_penyimpanan_gagal(): void
    {
        // Berkas ditulis lebih dulu, baru barisnya dibuat. Kalau bagian basis
        // data gagal, transaksi memutar balik barisnya — tapi berkas di cakram
        // tidak ikut terputar balik. Tanpa pembersihan eksplisit, tiap kegagalan
        // menyisakan berkas yang tidak dirujuk baris mana pun.
        // Kegagalan dipicu dari dalam transaksi, setelah berkas ditulis dan
        // barisnya dibuat — persis titik paling berbahaya pada alur ini.
        Document::created(function (): void {
            throw new RuntimeException('kegagalan basis data yang disengaja');
        });

        try {
            $this->actingAs($this->pengunggah)
                ->withoutExceptionHandling()
                ->post('/documents', $this->formulir([
                    'is_shared_to_all' => false,
                    'unit_ids' => [$this->divisi->id],
                ]));

            $this->fail('Pengecualian seharusnya diteruskan, bukan ditelan diam-diam.');
        } catch (RuntimeException) {
            // Diharapkan.
        }

        $this->assertSame(0, Document::count(), 'Baris dokumen harus ikut diputar balik.');
        $this->assertEmpty(
            Storage::disk('local')->allFiles('documents'),
            'Ada berkas tertinggal di penyimpanan setelah kegagalan.',
        );
    }

    // -- Batas ukuran ---------------------------------------------------------

    public function test_berkas_melebihi_batas_ditolak(): void
    {
        app(PengaturanService::class)->simpan('unggah.batas_kb', 1024);

        $this->actingAs($this->pengunggah)
            ->post('/documents', $this->formulir([
                'file' => UploadedFile::fake()->create('besar.pdf', 2048, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
        $this->assertEmpty(Storage::disk('local')->allFiles('documents'));
    }

    public function test_batas_dari_setelan_superadmin_mengalahkan_bawaan_config(): void
    {
        // Ini yang membuat halaman setelan bermakna: mengubah nilainya harus
        // benar-benar mengubah apa yang diterima server, bukan hanya angka yang
        // ditampilkan di formulir.
        config()->set('dms.berkas.ukuran_maksimum_kb', 1048576);
        app(PengaturanService::class)->simpan('unggah.batas_kb', 512);

        $this->actingAs($this->pengunggah)
            ->post('/documents', $this->formulir([
                'file' => UploadedFile::fake()->create('sedang.pdf', 900, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('file');
    }

    public function test_berkas_tepat_di_bawah_batas_diterima(): void
    {
        // Penjaga terhadap kesalahan pagar-tiang: batas yang meleset satu satuan
        // menolak berkas yang semestinya sah.
        app(PengaturanService::class)->simpan('unggah.batas_kb', 1024);

        $this->actingAs($this->pengunggah)
            ->post('/documents', $this->formulir([
                'file' => UploadedFile::fake()->create('pas.pdf', 1024, 'application/pdf'),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Document::count());
    }

    // -- Penjelasan jenjang jabatan -------------------------------------------

    public function test_formulir_menjelaskan_isi_tiap_jenjang_jabatan(): void
    {
        // Formulir menyebutkan "N orang akan dapat melihat dokumen ini" beserta
        // nama jabatannya. Angka itu hanya berguna bila benar — kalau meleset,
        // pengunggah mengambil keputusan berbagi berdasarkan informasi palsu.
        $atas = Jabatan::factory()->tingkat(1)->create(['nama' => 'Kepala']);
        User::factory()->count(2)->create(['jabatan_id' => $atas->id]);
        User::factory()->create(['jabatan_id' => $atas->id, 'is_active' => false]);

        $jenjang = collect(JenjangAkses::daftar())->keyBy('tingkat');

        $this->assertSame('Kepala', $jenjang[1]['jabatan'][0]['nama']);
        $this->assertSame(2, $jenjang[1]['jabatan'][0]['jumlah'], 'Akun nonaktif tidak boleh ikut dihitung.');
        $this->assertSame(2, $jenjang[1]['jumlah']);
    }

    public function test_jenjang_terurut_dari_yang_tertinggi(): void
    {
        // Antarmuka menghitung "ke atas" dengan membandingkan tingkat, dan
        // menampilkan hasilnya apa adanya. Urutan yang salah membuat daftar
        // "termasuk" tampil acak.
        Jabatan::factory()->tingkat(1)->create();
        Jabatan::factory()->tingkat(2)->create();

        $tingkat = array_column(JenjangAkses::daftar(), 'tingkat');
        $urut = $tingkat;
        sort($urut);

        $this->assertSame($urut, $tingkat);
        $this->assertSame(array_values(array_unique($tingkat)), $tingkat, 'Tiap tingkat harus muncul sekali saja.');
    }

    public function test_jabatan_nonaktif_tidak_ditawarkan_sebagai_jenjang(): void
    {
        Jabatan::factory()->tingkat(9)->create(['is_active' => false]);

        $this->assertNotContains(9, array_column(JenjangAkses::daftar(), 'tingkat'));
    }

    // -- Pencarian pengguna ---------------------------------------------------

    public function test_pencarian_pengguna_menolak_kata_terlalu_pendek(): void
    {
        $this->actingAs($this->pengunggah)
            ->getJson('/documents/cari-pengguna?cari=a')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_pencarian_pengguna_hanya_mengembalikan_akun_aktif(): void
    {
        User::factory()->create([
            'name' => 'Budi Aktif',
            'jabatan_id' => $this->pengunggah->jabatan_id,
            'unit_id' => $this->divisi->id,
        ]);
        User::factory()->create([
            'name' => 'Budi Nonaktif',
            'jabatan_id' => $this->pengunggah->jabatan_id,
            'unit_id' => $this->divisi->id,
            'is_active' => false,
        ]);

        $hasil = $this->actingAs($this->pengunggah)
            ->getJson('/documents/cari-pengguna?cari=Budi')
            ->assertOk()
            ->json();

        $this->assertCount(1, $hasil);
        $this->assertSame('Budi Aktif', $hasil[0]['nama']);
    }

    public function test_pencarian_pengguna_tidak_mengembalikan_diri_sendiri(): void
    {
        // Pengunggah selalu dapat melihat dokumennya sendiri, jadi memilih diri
        // sendiri sebagai penerima tidak menambah apa pun selain kebingungan.
        $this->pengunggah->update(['name' => 'Zulkarnain Penguji']);

        $this->actingAs($this->pengunggah)
            ->getJson('/documents/cari-pengguna?cari=Zulkarnain')
            ->assertExactJson([]);
    }
}
