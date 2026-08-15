<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\PengaturanService;

/**
 * Menghitung batas ukuran unggahan yang benar-benar berlaku.
 *
 * Batasnya berlapis tiga, dan yang menang selalu yang paling kecil:
 *
 * 1. **Setelan Superadmin** — dapat diubah lewat antarmuka, tersimpan di tabel
 *    `pengaturan`.
 * 2. **Bawaan aplikasi** — `config/dms.php`, dipakai selama Superadmin belum
 *    pernah mengubahnya.
 * 3. **Lingkungan** — `upload_max_filesize` dan `post_max_size` milik PHP,
 *    serta `client_max_body_size` pada server web.
 *
 * Lapis ketiga tidak dapat dilampaui setinggi apa pun Superadmin menyetelnya:
 * PHP menolak berkas sebelum Laravel sempat berjalan. Karena itu antarmuka
 * selalu menampilkan batas yang SESUNGGUHNYA berlaku, bukan yang diminta —
 * menampilkan angka yang tidak dapat ditepati justru lebih buruk daripada
 * angka yang kecil.
 *
 * Ini penting karena kalau ketiganya tidak selaras, kegagalannya senyap dan
 * menyesatkan:
 *
 * - Berkas melebihi `upload_max_filesize` ditolak PHP **sebelum** Laravel
 *   melihatnya. Yang sampai ke validasi adalah permintaan tanpa berkas, dan
 *   pesan yang muncul jadi "berkas wajib diisi" — bukan "berkas terlalu besar".
 * - Berkas melebihi `post_max_size` membuat PHP membuang **seluruh** isi POST,
 *   termasuk token CSRF. Laravel menangkapnya lewat `ValidatePostSize` dan
 *   mengembalikan 413, tapi tanpa penjelasan yang berarti bagi pengguna.
 *
 * Dengan menghitung batas sesungguhnya, pesan yang ditampilkan selalu jujur —
 * dan antarmuka dapat menolak berkas kebesaran sebelum satu byte pun terkirim.
 */
final class BatasUnggah
{
    /**
     * Ruang yang disisihkan dari `post_max_size` untuk medan formulir lain,
     * token, dan pembungkus multipart. Tanpa cadangan ini, berkas yang tepat
     * sebesar `post_max_size` tetap gagal karena keseluruhan permintaan
     * melampauinya.
     */
    private const CADANGAN_KB = 512;

    /**
     * Batas efektif dalam kilobyte.
     */
    public static function kilobyte(): ?int
    {
        $batas = [];

        // Batas aplikasi bersifat opsional. `null` berarti aplikasi menyerahkan
        // sepenuhnya kepada lingkungan.
        if (($aplikasi = self::batasAplikasiKilobyte()) !== null) {
            $batas[] = $aplikasi;
        }

        if (($unggah = self::iniKeKilobyte('upload_max_filesize')) !== null) {
            $batas[] = $unggah;
        }

        if (($post = self::iniKeKilobyte('post_max_size')) !== null) {
            $batas[] = max(1, $post - self::CADANGAN_KB);
        }

        // Benar-benar tanpa batas hanya mungkin bila PHP pun tidak membatasi —
        // keadaan yang wajar di VPS yang disetel longgar.
        return $batas === [] ? null : max(1, min($batas));
    }

    /**
     * Batas efektif dalam megabyte untuk ditampilkan. Null berarti tanpa batas.
     */
    public static function megabyte(): ?float
    {
        $kb = self::kilobyte();

        return $kb === null ? null : round($kb / 1024, 1);
    }

    /**
     * Keterangan siap tampil, mis. "20 MB" atau "tanpa batas".
     */
    public static function keterangan(): string
    {
        $mb = self::megabyte();

        return $mb === null ? 'tanpa batas' : "{$mb} MB";
    }

    /**
     * Aturan validasi ukuran untuk Form Request.
     *
     * Mengembalikan array kosong bila tidak ada batas — sehingga aturannya
     * memang tidak dipasang, bukan dipasang dengan nilai raksasa yang
     * menyesatkan pembaca kode.
     *
     * @return list<string>
     */
    public static function aturanValidasi(): array
    {
        $kb = self::kilobyte();

        return $kb === null ? [] : ["max:{$kb}"];
    }

    /**
     * Batas yang ditetapkan aplikasi, tanpa memperhitungkan lingkungan.
     */
    public static function batasAplikasiKilobyte(): ?int
    {
        // Setelan Superadmin lebih diutamakan; bawaan config dipakai selama
        // setelan itu belum pernah disentuh.
        return app(PengaturanService::class)->integer('unggah.batas_kb');
    }

    /**
     * Selisih antara yang dijanjikan aplikasi dan yang sanggup dilayani mesin.
     *
     * Dipakai formulir unggah untuk menampilkan peringatan. Aplikasi tidak
     * dihentikan — ia tetap berjalan dengan batas yang lebih kecil — tapi
     * penguji harus tahu bahwa yang sedang ia uji bukan batas sesungguhnya.
     */
    public static function keteranganBatasAplikasi(): string
    {
        $kb = self::batasAplikasiKilobyte();

        return $kb === null ? 'tanpa batas' : round($kb / 1024, 1).' MB';
    }

    /**
     * Apakah batas PHP lebih ketat daripada yang diinginkan aplikasi.
     *
     * Dipakai perintah diagnosa dan dokumentasi — bukan untuk menghentikan
     * aplikasi. Batas yang lebih kecil tetap berfungsi, hanya saja pengguna
     * tidak mendapat kelonggaran sebesar yang dijanjikan konfigurasi.
     */
    public static function dibatasiPhp(): bool
    {
        $aplikasi = self::batasAplikasiKilobyte();
        $efektif = self::kilobyte();

        if ($efektif === null) {
            return false;
        }

        return $aplikasi === null || $efektif < (int) $aplikasi;
    }

    /**
     * Mengubah notasi ringkas php.ini ("2M", "512K", "1G") menjadi kilobyte.
     *
     * Mengembalikan null bila nilainya kosong atau 0 — pada PHP, 0 berarti
     * tanpa batas, bukan "tidak boleh sama sekali".
     */
    private static function iniKeKilobyte(string $kunci): ?int
    {
        $nilai = trim((string) ini_get($kunci));

        if ($nilai === '' || $nilai === '0' || $nilai === '-1') {
            return null;
        }

        $angka = (int) $nilai;
        $satuan = strtoupper(substr($nilai, -1));

        return match ($satuan) {
            'G' => $angka * 1024 * 1024,
            'M' => $angka * 1024,
            'K' => $angka,
            // Tanpa satuan berarti byte.
            default => intdiv($angka, 1024),
        };
    }
}
