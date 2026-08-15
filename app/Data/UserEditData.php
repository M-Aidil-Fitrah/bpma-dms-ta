<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Keadaan akun sebagaimana dibutuhkan FORMULIR sunting (FR-26).
 *
 * `jabatan_id`/`unit_id`, bukan namanya — formulir harus dapat mencentang
 * ulang pilihan yang sedang berlaku (sama seperti `DocumentEditData`).
 */
#[TypeScript]
final class UserEditData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?int $jabatan_id,
        public ?int $unit_id,
        public bool $is_active,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            jabatan_id: $user->jabatan_id,
            unit_id: $user->unit_id,
            is_active: $user->is_active,
        );
    }
}
