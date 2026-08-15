<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentEditScope;
use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use App\Models\Category;
use App\Models\Document;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Dipakai pengujian, bukan untuk seed data.
 *
 * Seed data memerlukan sebaran mekanisme akses yang terkoordinasi antar
 * dokumen, sehingga disusun tersendiri di `DocumentSeeder`. Factory ini
 * menyediakan dokumen tunggal yang sah dengan pengaturan sesedikit mungkin,
 * supaya tes hanya perlu menyebut atribut yang benar-benar diujinya.
 *
 * Bawaannya sengaja TANPA mekanisme akses aktif: dokumen hanya terlihat oleh
 * pengunggahnya. Tes yang menguji keterlihatan harus menyalakan mekanismenya
 * sendiri secara eksplisit, sehingga tidak ada tes yang lolos karena kebetulan
 * bawaannya permisif.
 *
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tanggal = $this->faker->dateTimeBetween('-2 years', 'now');

        return [
            'nomor' => sprintf(
                '%03d/BPMA/UJI/%s/%s',
                $this->faker->numberBetween(1, 999),
                $this->faker->numberBetween(1, 12),
                $tanggal->format('Y'),
            ),
            'judul' => $this->faker->sentence(5),
            'category_id' => Category::factory(),
            'origin_unit_id' => Unit::factory(),
            'tanggal' => $tanggal->format('Y-m-d'),
            'masa_berlaku' => null,
            'status' => DocumentStatus::Berlaku,
            'deskripsi' => $this->faker->paragraph(),
            'file_path' => 'documents/'.$tanggal->format('Y/m').'/'.$this->faker->uuid().'.pdf',
            'file_name_original' => 'dokumen-uji.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(50_000, 5_000_000),
            'extracted_text' => null,
            'extraction_status' => ExtractionStatus::Completed,
            'is_shared_to_all' => false,
            'min_tingkat_akses' => null,
            'edit_scope' => DocumentEditScope::OwnerOnly,
            'uploaded_by' => User::factory(),
            'is_active' => true,
        ];
    }

    /** Mekanisme akses: bagikan ke semua pengguna internal. */
    public function dibagikanKeSemua(): static
    {
        return $this->state(['is_shared_to_all' => true]);
    }

    /** Mekanisme akses: bagikan ke jenjang jabatan tertentu ke atas. */
    public function untukJenjang(int $tingkat): static
    {
        return $this->state(['min_tingkat_akses' => $tingkat]);
    }

    /** Siapa pun yang boleh melihat, boleh pula menyunting. */
    public function suntingSesuaiAkses(): static
    {
        return $this->state(['edit_scope' => DocumentEditScope::MatchVisibility]);
    }

    public function kadaluarsa(): static
    {
        return $this->state([
            'status' => DocumentStatus::Kadaluarsa,
            'masa_berlaku' => now()->subMonth()->toDateString(),
        ]);
    }

    public function nonaktif(): static
    {
        return $this->state(['is_active' => false]);
    }
}
