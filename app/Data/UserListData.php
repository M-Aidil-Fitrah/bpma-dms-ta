<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use App\Support\Inisial;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Satu baris pada daftar manajemen pengguna (FEAT-13, FR-31).
 */
#[TypeScript]
final class UserListData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $jabatan,
        public ?string $unit,
        public bool $is_active,
        public string $inisial,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            jabatan: $user->jabatan?->nama,
            unit: $user->unit?->nama,
            is_active: $user->is_active,
            inisial: Inisial::dari($user->name),
        );
    }
}
