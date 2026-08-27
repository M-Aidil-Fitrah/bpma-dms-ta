<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Keadaan turunan PDF yang dipakai untuk pratinjau Office internal. */
#[TypeScript]
enum PreviewStatus: string
{
    /** Berkas bukan Office atau belum perlu turunan PDF. */
    case NotApplicable = 'not_applicable';

    /** Konversi LibreOffice sudah masuk antrean atau sedang berjalan. */
    case Processing = 'processing';

    /** PDF turunan tersedia dan dapat dibuka melalui rute terotorisasi. */
    case Ready = 'ready';

    /** Konversi telah gagal setelah seluruh percobaan job selesai. */
    case Failed = 'failed';
}
