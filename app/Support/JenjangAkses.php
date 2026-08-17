<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Jabatan;

/**
 * Menjelaskan arti setiap jenjang jabatan pada mekanisme akses (FR-40).
 *
 * Angka tingkat saja tidak berarti apa-apa bagi pengunggah. "Tingkat 2 ke atas"
 * menuntut ia mengingat urutan jenjang di kepalanya, lalu menerjemahkannya
 * sendiri menjadi daftar orang — dan salah menerjemahkan berarti dokumen
 * terbuka bagi pihak yang tidak semestinya. Karena itu setiap tingkat dikirim
 * lengkap dengan nama jabatan yang berada padanya beserta jumlah pemegangnya,
 * sehingga antarmuka dapat menyebutkan akibatnya secara harfiah.
 *
 * Jumlah dihitung dengan satu kueri beragregat, bukan satu kueri per jabatan —
 * banyaknya kueri tidak boleh tumbuh mengikuti banyaknya jabatan.
 */
final class JenjangAkses
{
    /**
     * Seluruh jenjang, terurut dari yang tertinggi (tingkat 1).
     *
     * @return list<array{
     *     tingkat: int,
     *     jabatan: list<array{nama: string, jumlah: int}>,
     *     jumlah: int
     * }>
     */
    public static function daftar(): array
    {
        $jabatan = Jabatan::query()
            ->active()
            ->withCount(['users' => fn ($query) => $query->active()])
            ->orderBy('tingkat_akses')
            ->orderBy('nama')
            ->get(['id', 'nama', 'tingkat_akses']);

        $perTingkat = [];

        foreach ($jabatan as $baris) {
            $tingkat = (int) $baris->tingkat_akses;

            $perTingkat[$tingkat] ??= ['tingkat' => $tingkat, 'jabatan' => [], 'jumlah' => 0];
            $perTingkat[$tingkat]['jabatan'][] = [
                'nama' => $baris->nama,
                'jumlah' => (int) $baris->users_count,
            ];
            $perTingkat[$tingkat]['jumlah'] += (int) $baris->users_count;
        }

        ksort($perTingkat);

        return array_values($perTingkat);
    }
}
