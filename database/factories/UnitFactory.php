<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Unit> */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nama' => 'Divisi '.$this->faker->unique()->words(2, true),
            'parent_id' => null,
            'tipe' => Unit::TIPE_DIVISI,
            'is_active' => true,
        ];
    }

    /** Unit tingkat atas — Sekretaris atau Deputi. */
    public function tingkatAtas(): static
    {
        return $this->state([
            'nama' => 'Deputi '.$this->faker->unique()->words(2, true),
            'tipe' => Unit::TIPE_DEPUTI,
        ]);
    }

    public function dibawah(Unit $induk): static
    {
        return $this->state(['parent_id' => $induk->id]);
    }

    public function nonaktif(): static
    {
        return $this->state(['is_active' => false]);
    }
}
