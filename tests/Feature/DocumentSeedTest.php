<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ExtractionStatus;
use App\Models\Document;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Menjaga sebaran seed dokumen tetap seperti yang dirancang.
 *
 * Seluruh pemeriksaan berada dalam SATU metode dan itu disengaja: seeder ini
 * menyalin 220 berkas ke cakram dan menjalankan OCR, sehingga memakan belasan
 * detik. Memecahnya menjadi sepuluh metode berarti membayar ongkos yang sama
 * sepuluh kali, dan rangkaian tes yang lambat akan berhenti dijalankan orang.
 */
final class DocumentSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_sebaran_seed_dokumen_sesuai_rancangan(): void
    {
        config()->set('dms.superadmin.email', 'superadmin@bpma.internal');
        config()->set('dms.superadmin.password', 'kata-sandi-uji');

        $this->seed(DatabaseSeeder::class);

        // -- Jumlah -----------------------------------------------------------
        $this->assertSame(220, Document::count());

        // -- Setiap mekanisme akses punya bahan uji ---------------------------
        $this->assertGreaterThan(0, Document::where('is_shared_to_all', true)->count());
        $this->assertGreaterThan(0, Document::whereNotNull('min_tingkat_akses')->count());
        $this->assertGreaterThan(0, Document::has('targetUnits')->count());
        $this->assertGreaterThan(0, Document::has('sharedUsers')->count());

        // -- Pembeda utama produk: tiga mekanisme aktif bersamaan -------------
        $tigaMekanisme = Document::with(['targetUnits', 'sharedUsers'])->get()
            ->filter(fn (Document $d): bool => collect([
                $d->is_shared_to_all,
                $d->min_tingkat_akses !== null,
                $d->targetUnits->isNotEmpty(),
                $d->sharedUsers->isNotEmpty(),
            ])->filter()->count() >= 3);

        $this->assertGreaterThanOrEqual(
            10,
            $tigaMekanisme->count(),
            'Perlu cukup dokumen berkombinasi tiga mekanisme untuk menguji rantai OR.',
        );

        // -- Kontrol negatif --------------------------------------------------
        // Tanpa dokumen yang tidak dapat dilihat siapa pun, sistem akses yang
        // selalu menjawab "boleh" akan terlihat lolos seluruh pengujian.
        $tanpaMekanisme = Document::where('is_shared_to_all', false)
            ->whereNull('min_tingkat_akses')
            ->doesntHave('targetUnits')
            ->doesntHave('sharedUsers')
            ->count();

        $this->assertGreaterThan(0, $tanpaMekanisme);

        // -- Setiap status ekstraksi punya contoh nyata ----------------------
        foreach (ExtractionStatus::cases() as $status) {
            $this->assertGreaterThan(
                0,
                Document::where('extraction_status', $status)->count(),
                "Status ekstraksi {$status->value} tidak punya contoh di seed.",
            );
        }

        $this->assertSame(
            0,
            Document::where('extraction_status', ExtractionStatus::Completed)
                ->where(fn ($query) => $query->whereNull('extracted_text')->orWhere('extracted_text', ''))
                ->count(),
            'Status selesai hanya boleh dipakai saat teks yang dapat dicari benar-benar tersedia.',
        );

        // -- Isi dokumen benar-benar dapat dicari ----------------------------
        $this->assertGreaterThan(
            0,
            Document::whereNotNull('extracted_text')->count(),
            'Tanpa isi terekstraksi, pencarian berbasis isi tidak dapat diuji.',
        );

        // -- Berkas fisik benar-benar ada ------------------------------------
        $hilang = Document::pluck('file_path')
            ->reject(fn (string $path): bool => Storage::disk('local')->exists($path));

        $this->assertCount(0, $hilang, 'Ada berkas dokumen yang tidak tersalin ke penyimpanan.');

        // -- Berkas tersimpan berdasarkan tahun dan bulan --------------------
        $this->assertMatchesRegularExpression(
            '#^documents/seed/\d{4}/\d{2}/[0-9a-f-]{36}\.\w+$#',
            Document::value('file_path'),
            'Berkas seed harus berada di ruang demo yang terpisah dari unggahan pengguna.',
        );

        // -- Bahan uji dasbor dan perintah terjadwal -------------------------
        $this->assertGreaterThan(0, Document::mendekatiMasaEvaluasi(30)->count());

        $this->assertGreaterThan(
            0,
            Document::where('status', 'berlaku')
                ->whereNotNull('masa_berlaku')
                ->whereDate('masa_berlaku', '<', now())
                ->count(),
            'Perlu dokumen yang masa berlakunya lewat untuk mendemokan transisi status otomatis.',
        );

        // Menjalankan seed ulang pada data demo yang telah ada tidak boleh
        // menduplikasi dokumen atau menghapus berkas yang masih dirujuk.
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(220, Document::count());
        $this->assertTrue(Storage::disk('local')->exists(Document::value('file_path')));
    }
}
