<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentFolder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Dipakai pengujian, bukan untuk seed data.
 *
 * Factory ini menyediakan folder tunggal yang sah dengan pengaturan sesedikit mungkin,
 * supaya tes hanya perlu menyebut atribut yang benar-benar diujinya.
 *
 * @extends Factory<DocumentFolder>
 */
class DocumentFolderFactory extends Factory
{
    protected $model = DocumentFolder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'name_normalized' => fn (array $attrs) => mb_strtolower($attrs['name']),
            'sharing_restricted' => false,
        ];
    }
}
