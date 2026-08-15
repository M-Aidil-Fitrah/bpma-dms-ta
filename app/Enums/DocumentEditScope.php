<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Cakupan wewenang menyunting sebuah dokumen.
 *
 * Independen dari mekanisme akses melihat — sebuah dokumen bisa terlihat banyak
 * orang tapi hanya boleh disunting pengunggahnya (`Catatan_Audit.md` isu #1).
 *
 * `MatchVisibility` memakai ulang scope `Document::visibleTo()`, sehingga aturan
 * "siapa yang boleh menyunting" tidak pernah menyimpang dari "siapa yang boleh
 * melihat".
 */
#[TypeScript]
enum DocumentEditScope: string
{
    case OwnerOnly = 'owner_only';
    case MatchVisibility = 'match_visibility';

    public function label(): string
    {
        return match ($this) {
            self::OwnerOnly => 'Hanya saya',
            self::MatchVisibility => 'Sama seperti akses',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OwnerOnly => 'Hanya Anda yang dapat mengubah dokumen ini.',
            self::MatchVisibility => 'Siapa pun yang dapat melihat dokumen ini juga dapat mengubahnya.',
        };
    }
}
