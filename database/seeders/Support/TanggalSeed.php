<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use Carbon\CarbonImmutable;

/**
 * Titik acuan waktu tetap untuk seluruh seeder dokumen dan Ruang Kerja.
 *
 * "Tanggal terbaru" pada data seed dikunci ke titik ini — bukan `now()`
 * server — supaya dokumen "mendekati masa evaluasi", retensi Sampah, dan
 * entri Terbaru selalu identik setiap `migrate:fresh --seed` dijalankan, di
 * laptop mana pun dan kapan pun perintahnya dieksekusi.
 */
final class TanggalSeed
{
    public static function sekarang(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-08-21 09:00:00');
    }
}
