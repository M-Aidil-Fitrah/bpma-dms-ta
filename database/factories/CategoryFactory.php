<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nama' => $this->faker->unique()->words(2, true),
            'deskripsi' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(['is_active' => false]);
    }
}
