<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Inisial nama untuk avatar berbasis huruf.
 *
 * Prototype ini tidak menyimpan foto pengguna, sehingga avatar dibentuk dari
 * inisial. Dihitung di backend, bukan di komponen React, supaya "Cut Nurhaliza"
 * menghasilkan "CN" yang sama di mana pun namanya muncul — daftar dokumen,
 * bilah atas, maupun riwayat aktivitas.
 */
final class Inisial
{
    public static function dari(?string $nama): string
    {
        $bagian = preg_split('/\s+/', trim((string) $nama)) ?: [];

        $inisial = collect($bagian)
            ->filter()
            ->take(2)
            ->map(static fn (string $kata): string => mb_strtoupper(mb_substr($kata, 0, 1)))
            ->implode('');

        return $inisial !== '' ? $inisial : '?';
    }
}
