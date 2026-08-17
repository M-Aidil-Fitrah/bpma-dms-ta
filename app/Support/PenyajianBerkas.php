<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Aturan penyajian berkas unggahan ke peramban.
 *
 * Berkas di sini diunggah pengguna, dan isinya tidak pernah dapat dipercaya.
 * Menyajikannya dengan `Content-Disposition: inline` berarti menyuruh peramban
 * MENJALANKANNYA pada asal (origin) yang sama dengan aplikasi. Untuk berkas
 * HTML atau SVG, itu setara memberi siapa pun yang boleh mengunggah kemampuan
 * menjalankan skrip di dalam sesi orang lain: skrip tersebut dapat membaca
 * token CSRF, lalu bertindak atas nama korban — termasuk membuka dan mengirim
 * keluar setiap dokumen yang dapat korban akses.
 *
 * Karena itu penyajian inline memakai daftar-boleh, bukan daftar-larang.
 * Daftar-larang selalu tertinggal satu langkah: setiap tipe berkas baru yang
 * dapat memuat skrip harus diingat untuk ditambahkan, dan yang terlupa menjadi
 * lubang. Dengan daftar-boleh, tipe yang tidak dikenal otomatis diperlakukan
 * sebagai unduhan — aman secara bawaan.
 */
final class PenyajianBerkas
{
    /**
     * Tipe yang boleh ditampilkan langsung di peramban.
     *
     * `image/svg+xml` sengaja TIDAK ada di sini. SVG adalah dokumen XML yang
     * dapat memuat `<script>`, sehingga sama berbahayanya dengan HTML meski
     * terlihat seperti gambar biasa.
     *
     * @var list<string>
     */
    public const AMAN_INLINE = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'text/plain',
    ];

    public static function bolehInline(?string $mime): bool
    {
        return $mime !== null && in_array(self::normalkan($mime), self::AMAN_INLINE, true);
    }

    /**
     * Tipe yang dikirim di header, bukan yang tersimpan apa adanya.
     *
     * Berkas yang tidak boleh tampil inline diberi tipe generik supaya peramban
     * tidak punya alasan menafsirkannya sebagai dokumen yang dapat dijalankan.
     */
    public static function tipeAman(?string $mime): string
    {
        return self::bolehInline($mime)
            ? self::normalkan((string) $mime)
            : 'application/octet-stream';
    }

    /**
     * Header yang menyertai setiap penyajian berkas unggahan.
     *
     * @return array<string, string>
     */
    public static function headerKeamanan(): array
    {
        return [
            // Tanpa ini peramban boleh menebak sendiri tipe berkas dari isinya
            // dan mengabaikan Content-Type yang kita kirim — sehingga berkas
            // yang sengaja diberi tipe generik tetap dieksekusi sebagai HTML.
            'X-Content-Type-Options' => 'nosniff',

            // Jaring pengaman lapis kedua: sekalipun ada yang lolos tampil
            // sebagai HTML, `sandbox` menempatkannya pada asal buntu sehingga
            // ia tidak dapat menyentuh sesi maupun data aplikasi.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",

            // Berkas dokumen tidak pernah boleh disimpan cache bersama, mis.
            // oleh proxy perusahaan — isinya bergantung pada siapa yang meminta.
            'Cache-Control' => 'private, no-store, max-age=0',
        ];
    }

    /**
     * Respons berkas dengan dukungan HTTP Range (`Accept-Ranges` + 206
     * Partial Content) — video/audio dapat di-*seek* dan PDF besar dimuat
     * progresif per potongan, bukan mengunduh seluruh berkas dulu setiap
     * kali. `Storage::response()`/`download()` bawaan Laravel memakai
     * `StreamedResponse` yang selalu mengirim seluruh isi dan mengabaikan
     * header `Range` sama sekali — `BinaryFileResponse` Symfony menanganinya
     * otomatis lewat `prepare()`, yang dipanggil framework untuk setiap
     * respons yang dikembalikan controller.
     *
     * @param  string  $pathAbsolut  Jalur filesystem asli, BUKAN jalur relatif disk Storage.
     */
    public static function respons(string $pathAbsolut, string $nama, ?string $mime, string $disposisi): BinaryFileResponse
    {
        // `public: false` mencegah Symfony menyetel `Cache-Control: public`
        // bawaannya — header keamanan di bawah yang menentukan, bukan default
        // library.
        $respons = new BinaryFileResponse($pathAbsolut, 200, [], public: false);
        $respons->setContentDisposition($disposisi, $nama);
        $respons->headers->set('Content-Type', self::tipeAman($mime));

        foreach (self::headerKeamanan() as $kunci => $nilai) {
            $respons->headers->set($kunci, $nilai);
        }

        return $respons;
    }

    /**
     * Membuang parameter seperti "; charset=utf-8" dan menyeragamkan huruf.
     */
    private static function normalkan(string $mime): string
    {
        return strtolower(trim(explode(';', $mime, 2)[0]));
    }
}
