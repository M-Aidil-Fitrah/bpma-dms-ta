<?php

declare(strict_types=1);

namespace App\Enums;

/** Jenis perubahan yang membentuk satu revisi dokumen immutable. */
enum DocumentVersionKind: string
{
    case Content = 'content';
    case Metadata = 'metadata';
    case Restoration = 'restoration';
}
