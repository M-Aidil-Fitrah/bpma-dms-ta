<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;

/**
 * `php artisan serve` dengan batas unggahan yang sudah sesuai aplikasi.
 *
 * `upload_max_filesize` dan `post_max_size` bersifat `PHP_INI_PERDIR` — tidak
 * dapat diubah dari dalam PHP saat sudah berjalan. Satu-satunya cara mengangkat
 * batasnya adalah menyerahkannya ke proses PHP saat dinyalakan.
 *
 * Tanpa penyesuaian ini, `php.ini` bawaan sebagian distribusi hanya mengizinkan
 * 2 MB. Unggahan yang lebih besar ditolak PHP **sebelum** Laravel berjalan, dan
 * pesan yang sampai ke pengguna menjadi "berkas wajib diisi" — menyesatkan, dan
 * berbeda-beda antar laptop tim.
 *
 * Menimpanya di sini, bukan menuliskan flag di README, karena instruksi yang
 * harus diingat manusia cepat atau lambat terlupa. Perintah `php artisan dev`
 * pun ikut terbantu: ia menyalakan servernya lewat `php artisan serve` yang
 * sama.
 *
 * Yang TIDAK dapat diperbaiki dari sini: `client_max_body_size` pada nginx di
 * VPS. Itu tetap harus disetel manual — lihat README.
 */
final class ServeCommand extends BaseServeCommand
{
    /**
     * Ruang tambahan di atas batas berkas, untuk medan formulir lain, token,
     * dan pembungkus multipart.
     */
    private const CADANGAN_KB = 51200; // 50 MB

    /**
     * @return array<int, string>
     */
    protected function serverCommand()
    {
        $perintah = parent::serverCommand();

        // Disisipkan tepat setelah binary PHP, sebelum `-S` — PHP menuntut
        // seluruh opsi berada sebelum argumen server.
        array_splice($perintah, 1, 0, $this->opsiIni());

        return $perintah;
    }

    /**
     * @return list<string>
     */
    private function opsiIni(): array
    {
        $batasKb = (int) config('dms.dokumen.ukuran_tertinggi_kb', 2097152);
        $totalKb = $batasKb + self::CADANGAN_KB;

        return [
            '-d', "upload_max_filesize={$batasKb}K",
            '-d', "post_max_size={$totalKb}K",
            // Unggahan dialirkan ke berkas sementara di disk, jadi memori tidak
            // perlu sebesar berkasnya. Yang dinaikkan hanya secukupnya untuk
            // pemrosesan permintaan.
            '-d', 'memory_limit=512M',
            // Unggahan besar pada koneksi lambat butuh waktu; batas waktu
            // eksekusi tidak boleh memotongnya di tengah jalan.
            '-d', 'max_execution_time=0',
        ];
    }
}
