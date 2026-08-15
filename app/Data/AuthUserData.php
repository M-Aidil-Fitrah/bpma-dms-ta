<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use App\Support\Inisial;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Pengguna yang sedang masuk, dibagikan ke setiap halaman.
 *
 * Jabatan dan unit ikut dimuat di sini karena hampir setiap halaman
 * membutuhkannya — untuk sapaan, inisial avatar, dan penentuan menu mana yang
 * ditampilkan. Memuatnya sekali di satu tempat mencegah tiap controller
 * mengambil relasi yang sama berulang-ulang.
 *
 * `password` dan `remember_token` tidak pernah sampai ke sini: DTO hanya
 * memuat medan yang disebut eksplisit, sehingga kebocoran atribut sensitif
 * tidak mungkin terjadi karena kelalaian.
 */
#[TypeScript]
final class AuthUserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $jabatan,
        public ?int $tingkat_akses,
        public ?string $unit,
        public bool $is_superadmin,
        /** Inisial untuk avatar, mis. "Fitri Handayani" menjadi "FH". */
        public string $initials,
    ) {}

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            jabatan: $user->jabatan?->nama,
            tingkat_akses: $user->jabatan?->tingkat_akses,
            unit: $user->unit?->nama,
            is_superadmin: $user->isSuperadmin(),
            initials: Inisial::dari($user->name),
        );
    }
}
