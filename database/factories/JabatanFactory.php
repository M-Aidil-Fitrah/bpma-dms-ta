<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Jabatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Jabatan> */
class JabatanFactory extends Factory
{
    protected $model = Jabatan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nama' => $this->faker->unique()->jobTitle(),
            'tingkat_akses' => 4,
            'is_active' => true,
        ];
    }

    /** @param int $tingkat 1 = tertinggi */
    public function tingkat(int $tingkat): static
    {
        return $this->state(['tingkat_akses' => $tingkat]);
    }
}
