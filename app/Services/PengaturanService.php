<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pengaturan;
use Illuminate\Support\Facades\Cache;

/**
 * Membaca dan menulis setelan aplikasi yang dapat diubah Superadmin.
 *
 * Berlapis dua: nilai bawaan hidup di `config/dms.php`, dan tabel `pengaturan`
 * hanya menyimpan yang benar-benar disunting. Ketiadaan baris berarti "masih
 * memakai bawaan", bukan "kosong" — sehingga memperbarui bawaan di kode tetap
 * berpengaruh pada pemasangan yang belum pernah menyentuh setelan itu.
 *
 * Seluruh setelan dibaca sekali per permintaan lalu disimpan di cache. Tanpa
 * itu, satu setelan yang dibaca di beberapa tempat menghasilkan beberapa query
 * untuk data yang sama persis.
 */
final class PengaturanService
{
    private const KUNCI_CACHE = 'pengaturan.semua';

    /**
     * Daftar setelan yang boleh diubah lewat antarmuka, beserta jalur nilai
     * bawaannya di `config/dms.php`.
     *
     * Daftar tertutup ini penting: tanpanya, kunci apa pun yang dikirim dari
     * formulir akan tersimpan, dan tabel setelan lambat laun terisi baris yang
     * tidak pernah dibaca siapa pun.
     *
     * @var array<string, string>
     */
    public const DIIZINKAN = [
        'unggah.batas_kb' => 'dms.dokumen.ukuran_maksimum_kb',
        'dokumen.per_halaman' => 'dms.dokumen.per_halaman',
    ];

    /**
     * Nilai setelan, atau nilai bawaannya bila belum pernah diubah.
     */
    public function ambil(string $kunci): mixed
    {
        $tersimpan = $this->semua()[$kunci] ?? null;

        if ($tersimpan !== null) {
            return $tersimpan;
        }

        $jalurBawaan = self::DIIZINKAN[$kunci] ?? null;

        return $jalurBawaan === null ? null : config($jalurBawaan);
    }

    /**
     * Nilai setelan sebagai bilangan bulat, atau null bila tidak berlaku.
     */
    public function integer(string $kunci): ?int
    {
        $nilai = $this->ambil($kunci);

        return $nilai === null || $nilai === '' ? null : (int) $nilai;
    }

    /**
     * Menyimpan setelan. Nilai null mengembalikannya ke bawaan.
     */
    public function simpan(string $kunci, mixed $nilai, ?int $olehUserId = null): void
    {
        if (! array_key_exists($kunci, self::DIIZINKAN)) {
            return;
        }

        if ($nilai === null || $nilai === '') {
            // Barisnya dihapus, bukan diisi null — supaya setelan itu kembali
            // mengikuti bawaan, termasuk bila bawaannya kelak diubah di kode.
            Pengaturan::query()->where('kunci', $kunci)->delete();
        } else {
            Pengaturan::updateOrCreate(
                ['kunci' => $kunci],
                ['nilai' => (string) $nilai, 'diubah_oleh' => $olehUserId],
            );
        }

        $this->lupakanCache();
    }

    /**
     * Seluruh setelan yang tersimpan, dipetakan kunci ke nilai.
     *
     * @return array<string, string>
     */
    public function semua(): array
    {
        return Cache::rememberForever(
            self::KUNCI_CACHE,
            fn (): array => Pengaturan::query()->pluck('nilai', 'kunci')->all(),
        );
    }

    public function lupakanCache(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }
}
