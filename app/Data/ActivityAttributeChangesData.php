<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Perubahan atribut yang aman ditampilkan pada riwayat aktivitas.
 *
 * Nilai audit dinormalisasi menjadi teks di satu tempat. Antarmuka tidak
 * menerima JSON bebas atau perlu menebak bentuk `old`/`attributes` lagi.
 */
#[TypeScript]
final class ActivityAttributeChangesData extends Data
{
    /**
     * @param  array<string, string>  $lama
     * @param  array<string, string>  $baru
     */
    public function __construct(
        public array $lama,
        public array $baru,
    ) {}

    /**
     * @param  array{old?: array<string, mixed>, attributes?: array<string, mixed>}  $changes
     */
    public static function fromChanges(array $changes): self
    {
        return new self(
            lama: self::normalise($changes['old'] ?? []),
            baru: self::normalise($changes['attributes'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private static function normalise(array $values): array
    {
        $normalised = [];

        foreach ($values as $field => $value) {
            $normalised[(string) $field] = match (true) {
                $value === null => '—',
                is_bool($value) => $value ? 'Ya' : 'Tidak',
                is_scalar($value) => (string) $value,
                default => json_encode($value, JSON_THROW_ON_ERROR),
            };
        }

        return $normalised;
    }
}
