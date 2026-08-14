<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Status keberlakuan dokumen.
 *
 * Nilainya sengaja tetap dari kode, bukan data dinamis seperti jabatan atau
 * unit — menjadikannya dinamis menuntut rule-engine dan merupakan perubahan
 * lingkup besar, bukan penambahan fitur kecil (`PRD.md` §2.7).
 *
 * Perpindahan Berlaku → Kadaluarsa terjadi otomatis lewat perintah terjadwal
 * harian saat `masa_berlaku` terlewati (FR-53).
 */
#[TypeScript]
enum DocumentStatus: string
{
    case Berlaku = 'berlaku';
    case Kadaluarsa = 'kadaluarsa';

    public function label(): string
    {
        return match ($this) {
            self::Berlaku => 'Berlaku',
            self::Kadaluarsa => 'Kadaluarsa',
        };
    }
}
