# BPMA DMS — Prototype Document Management System

Aplikasi web internal untuk menyimpan, mengelompokkan, mencari, dan memantau
dokumen digital BPMA secara terstruktur.

> **Seluruh data di dalam aplikasi ini adalah data dummy.** Prototype ini tidak
> ditujukan untuk dokumen resmi, rahasia, maupun data pribadi pegawai
> sesungguhnya, dan hak aksesnya adalah simulasi — bukan kontrol keamanan
> tingkat produksi.

---

## Prasyarat

| Kebutuhan | Versi | Catatan |
|---|---|---|
| PHP | 8.3+ | |
| Composer | 2.x | |
| MySQL / MariaDB | 8.0+ / 10.5+ | **Wajib.** SQLite tidak mendukung index FULLTEXT yang dipakai pencarian isi dokumen |
| Node.js | 20+ | |
| Tesseract OCR | 5.x | Untuk OCR berkas gambar. Dipasang di level sistem operasi, bukan lewat Composer |
| LibreOffice | 7.x+ | Mengonversi Word, Excel, dan PowerPoint menjadi PDF pratinjau privat |
| Ghostscript | 10.x+ | Membuat gambar mini dari halaman pertama PDF hasil konversi |

### Memasang Tesseract

| Sistem Operasi | Perintah |
|---|---|
| Windows | Installer UB-Mannheim — centang bahasa **Indonesian** saat memasang, lalu tambahkan ke PATH |
| macOS | `brew install tesseract tesseract-lang` |
| Ubuntu / Debian | `sudo apt install tesseract-ocr tesseract-ocr-ind` |
| Fedora | `sudo dnf install tesseract tesseract-langpack-ind` |

Verifikasi sebelum lanjut:

```bash
tesseract --version
```

### Memasang perkakas pratinjau Office

| Sistem Operasi | Perintah |
|---|---|
| macOS | `brew install --cask libreoffice ghostscript` |
| Ubuntu / Debian | `sudo apt install libreoffice ghostscript` |
| Fedora | `sudo dnf install libreoffice ghostscript` |

Verifikasi sebelum deploy:

```bash
libreoffice --version
gs --version
```

---

## Pemasangan

```bash
git clone <url-repositori> bpma-dms
cd bpma-dms

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Buat database, lalu sesuaikan `DB_*` di `.env`:

```sql
CREATE DATABASE `bpma-dms` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bpma_dms'@'127.0.0.1' IDENTIFIED BY 'ganti-dengan-rahasia-unik';
GRANT ALL PRIVILEGES ON `bpma-dms`.* TO 'bpma_dms'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Gunakan akun `bpma_dms` tersebut di produksi; jangan gunakan `root`. Ganti
placeholder kata sandi sebelum mengeksekusi SQL dan simpan nilainya hanya di
secret manager/environment host.

Isi kredensial Superadmin di `.env` — akun ini satu-satunya jalan masuk pertama
ke aplikasi, karena **tidak ada registrasi publik**:

```env
SUPERADMIN_NAME="Administrator BPMA"
SUPERADMIN_EMAIL=superadmin@bpma.internal
SUPERADMIN_PASSWORD=ganti-dengan-kata-sandi-kuat
```

Untuk server produksi, mulai dari `.env.production.example`, bukan
`.env.example`. Isi `APP_KEY`, kredensial database non-root, dan kredensial
Superadmin melalui secret manager/environment host; aplikasi menolak boot bila
`APP_ENV=production` dengan `APP_DEBUG=true`, dan kata sandi Superadmin harus
minimal 16 karakter. Sebelum layanan dibuka ke pengguna, rotasi secret awal
Superadmin menjadi nilai unik, lalu jalankan `php artisan dms:superadmin` untuk
menyelaraskan kredensial akun dengan secret yang sudah dirotasi.

Untuk rilis Docker Compose atau Supervisor, termasuk urutan migrasi, verifikasi
pasca-rilis, dan rollback yang aman, ikuti [panduan deployment](./DEPLOYMENT.md).

Jalankan migrasi dan seed:

```bash
php artisan migrate --seed
```

---

## Menjalankan Aplikasi

**Empat proses berjalan bersamaan.** Menjalankan `php artisan serve` saja tidak
cukup — dua proses terakhir menangani pekerjaan latar yang tidak akan berjalan
tanpanya:

```bash
php artisan serve                          # 1. Server aplikasi
npm run dev                                # 2. Server aset frontend
php artisan queue:work --queue=default,thumbnail  # 3. Ekstraksi teks, OCR & gambar mini
php artisan schedule:work                  # 4. Perpindahan status dokumen ke Kadaluarsa
```

Kalau lupa menjalankan nomor 3 atau 4, aplikasi tetap terbuka dan terlihat
normal — tapi status ekstraksi dokumen akan macet selamanya di "Memproses", dan
dokumen yang masa berlakunya lewat tidak pernah berpindah status. Keduanya
terlihat seperti bug, padahal hanya prosesnya yang belum jalan.

### Jalan pintas: `composer run dev`

Empat proses di atas juga bisa dijalankan sekaligus lewat satu perintah:

```bash
composer run dev
```

Ini menjalankan `php artisan dev` bawaan Laravel, yang di proyek ini sudah
dilengkapi lewat `AppServiceProvider::boot()` supaya benar-benar mencakup
kelima proses (server, Vite, log, **antrean `default`+`thumbnail`**, dan
**scheduler**) — bawaan Laravel sendiri hanya mendaftarkan `queue:listen`
tanpa `--queue` dan tidak mendaftarkan scheduler sama sekali, yang kalau
dibiarkan menimbulkan persis dua gejala di atas. Cocok untuk kerja
sehari-hari; empat proses manual di atas tetap berguna kalau Anda perlu
mengontrol atau menghentikan satu proses secara terpisah.

Gambar mini/pratinjau Office berjalan di antrean **`thumbnail`**, terpisah
dari ekstraksi teks/OCR di antrean **`default`** — sengaja dipisah supaya satu
OCR PDF pindaian yang berjalan lama (bisa sampai 15 menit) tidak menahan
gambar mini dokumen lain yang seharusnya cepat selesai. Satu proses
`queue:work` yang memantau keduanya (seperti perintah di atas) sudah cukup
untuk pemakaian lokal/tim kecil. Di VPS dengan trafik unggah yang lebih
padat, jalankan **dua** proses `queue:work` terpisah (masing-masing program
Supervisor/systemd sendiri) — satu `--queue=default`, satu lagi
`--queue=thumbnail` — supaya keduanya benar-benar berjalan paralel, bukan
sekadar bergiliran dalam satu proses.

### Backfill pratinjau Office lama

Setelah deploy migrasi ini, masukkan arsip Office lama ke antrean secara
bertahap; perintah hanya menandai dan mengantrikan dokumen yang belum memiliki
PDF pratinjau, tanpa mengubah berkas asli:

```bash
php artisan documents:backfill-previews --chunk=50
```

Jalankan ulang aman untuk dokumen yang masih `processing` atau sudah `ready`.
Untuk mencoba ulang dokumen yang sebelumnya gagal, setelah penyebabnya
diperbaiki, gunakan `php artisan documents:backfill-previews --retry-failed`.

### Status dokumen kedaluwarsa

Scheduler menjalankan `documents:update-expired-status` setiap hari pukul
**00.05**. Command ini hanya memindahkan dokumen berstatus **Berlaku** yang
masa berlakunya sebelum hari ini ke **Kadaluarsa**, lalu mencatat setiap
perubahan sebagai aktivitas otomatis oleh Sistem. Untuk demonstrasi atau
pengecekan manual, jalankan:

```bash
php artisan documents:update-expired-status
```

Command ini aman dijalankan berulang; dokumen yang sudah Kadaluarsa tidak
akan diubah atau dicatat lagi.

Scheduler juga mengosongkan aktivitas yang lebih tua dari **365 hari** setiap
pukul **00.40**, setelah purge Sampah pukul **00.20**. Pembersihan memakai
`activitylog:clean --force` agar berjalan non-interaktif di produksi dan tidak
boleh dijalankan dari lebih dari satu scheduler pada saat yang sama. Pastikan
backup tersedia sebelum mengubah kebijakan retensi karena penghapusan ini tidak
dapat dipulihkan dari aplikasi.

### Menjaga `queue:work` tetap hidup di VPS

Proses nomor 3 tidak boleh berhenti begitu sesi terminal ditutup, dan
Laravel sendiri tidak menyalakannya ulang kalau prosesnya mati. Pakai
**Supervisor** atau **systemd** supaya proses itu otomatis dijalankan lagi.

Untuk driver `database`, pertahankan `DB_QUEUE_RETRY_AFTER` lebih besar dari
timeout job terpanjang (default proyek: 1200 detik vs OCR 900 detik). Kedua job
berat memakai kunci unik per dokumen; pada produksi, gunakan cache lock bersama
seperti `database` atau Redis—jangan `array`, karena lock `array` tidak dibagi
antar worker.

Kalau workernya sempat mati, tidak ada data yang rusak — dokumen yang
terlanjur diunggah selama itu hanya menunggu di tabel `jobs` dengan status
tetap "Memproses" sampai workernya hidup kembali dan memprosesnya.

---

## Batas Ukuran Unggahan

Aplikasi menetapkan batas **1 GB**, dan angka itu **berlaku sama di semua
lingkungan** — laptop pengembangan maupun VPS. Batas yang berbeda-beda per mesin
membuat pengujian tidak dapat dipercaya: berkas yang lolos di laptop bisa
ditolak di server tanpa satu pun perubahan kode.

Sebagian besar setelannya **sudah ikut di repositori** dan berlaku otomatis
saat di-deploy. Yang benar-benar perlu disentuh manual hanya satu, dan itu pun
cuma pada VPS ber-nginx.

### Apa yang perlu Anda lakukan, per lingkungan

| Lingkungan | Yang perlu disetel manual |
|---|---|
| **Laptop tim** (`php artisan serve`) | **Tidak ada** |
| **cPanel / Plesk / CyberPanel / aaPanel** | **Tidak ada** |
| **Shared hosting** (PHP-FPM) | **Tidak ada** |
| **VPS: Apache** | **Tidak ada** |
| **VPS: nginx + PHP-FPM** | Satu blok di konfigurasi nginx |

### Kenapa sebagian besar tidak perlu disetel

Tiga berkas menanganinya, masing-masing untuk lingkungan yang berbeda:

| Berkas | Menangani | Berlaku pada |
|---|---|---|
| `app/Console/Commands/ServeCommand.php` | Menyalakan PHP dengan batas yang benar | `php artisan serve` di laptop |
| `public/.user.ini` | `upload_max_filesize`, `post_max_size` | PHP-FPM & CGI — cPanel, Plesk, shared hosting, mayoritas VPS |
| `public/.htaccess` | `LimitRequestBody` dan `php_value` | Apache, baik mod_php maupun FPM |

`public/.user.ini` itu yang menutup sebagian besar kasus: PHP membacanya langsung
dari direktori akar dokumen, tanpa akses root dan tanpa membuka panel apa pun.
Perubahannya tersimpan di cache selama lima menit, jadi efeknya bisa tertunda
sebentar setelah deploy.

### VPS ber-nginx — satu-satunya yang manual

`client_max_body_size` milik nginx tidak dapat diatur dari sisi PHP. Nginx
menolak permintaan besar **sebelum** PHP dijalankan, jadi berkas apa pun di
`public/` tidak akan terbaca.

Tambahkan ke blok `server` atau `location`:

```nginx
client_max_body_size 1074M;

# Unggahan 1 GB pada koneksi lambat butuh waktu. Tanpa tiga baris ini,
# unggahan besar terputus di tengah dengan galat 504 yang terlihat acak —
# karena bergantung kecepatan jaringan penggunanya, bukan pada berkasnya.
client_body_timeout  600s;
proxy_read_timeout   600s;
fastcgi_read_timeout 600s;
```

Lalu `sudo nginx -t && sudo systemctl reload nginx`.

### Kalau memakai panel dan tetap ingin memastikan

Bila karena suatu hal `.user.ini` tidak terbaca, panel biasanya menyediakan
jalur GUI:

| Panel | Jalur |
|---|---|
| cPanel | Software → **MultiPHP INI Editor** → pilih domain → ubah `upload_max_filesize` dan `post_max_size` |
| Plesk | Websites & Domains → **PHP Settings** |
| CyberPanel | PHP → **Edit PHP Configs** → Advanced |
| aaPanel | App Store → PHP → **Settings** → Configuration |

### Kalau lingkungan belum disetel

Aplikasi tidak diam dan tidak gagal secara misterius. Formulir unggah membaca
batas yang sedang benar-benar berlaku, menampilkannya, dan memperingatkan bahwa
angka itu di bawah yang seharusnya. Tanpa mekanisme ini, berkas kebesaran
ditolak PHP **sebelum** Laravel sempat berjalan — dan pesan yang muncul menjadi
"berkas wajib diisi", bukan "berkas terlalu besar".

### Di laptop pengembangan — tidak perlu disetel

`php artisan serve` sudah otomatis menyalakan PHP dengan batas yang sesuai
konfigurasi aplikasi. Tidak ada flag yang perlu diingat, dan `php.ini` sistem
tidak perlu disentuh.

Ini bukan kebetulan: perintah `serve` bawaan Laravel ditimpa di
`app/Console/Commands/ServeCommand.php`, karena `upload_max_filesize` bersifat
`PHP_INI_PERDIR` dan mustahil diubah dari dalam kode setelah PHP berjalan.
Angkanya dibaca dari `config/dms.php`, sehingga tidak ada dua tempat yang dapat
berselisih.

`php artisan dev` ikut terbantu — ia menyalakan servernya lewat perintah yang
sama.

Memeriksa batas yang sedang berlaku di dalam server:

```bash
php -d upload_max_filesize=1048576K -r 'echo ini_get("upload_max_filesize");'
```

---

## Mengelola Akun Superadmin

Superadmin adalah satu-satunya jalan masuk pertama ke aplikasi — tidak ada
registrasi publik. Akunnya dibuat otomatis oleh `migrate --seed`, tapi tersedia
pula perintah tersendiri:

```bash
php artisan dms:superadmin
```

Perintah ini membaca `SUPERADMIN_*` dari `.env`, aman dijalankan berulang kali,
dan berguna untuk tiga keadaan:

| Keadaan | Yang dilakukan |
|---|---|
| Mengganti kata sandi Superadmin | Ubah `SUPERADMIN_PASSWORD` di `.env`, jalankan perintahnya |
| Memasang tanpa data dummy | Jalankan `php artisan migrate` lalu perintah ini — tanpa `--seed`, sehingga 220 dokumen contoh tidak ikut terbawa |
| Terkunci di luar aplikasi | Perbaiki `.env`, jalankan perintahnya |

---

## Perintah Pengembangan

```bash
npm run dev          # Server pengembangan frontend
npm run build        # Build produksi (menjalankan pemeriksaan TypeScript lebih dulu)
npm run typecheck    # Pemeriksaan TypeScript saja

php artisan test     # Menjalankan seluruh tes
./vendor/bin/pint    # Merapikan gaya penulisan PHP
./vendor/bin/pint --test   # Memeriksa tanpa mengubah berkas
```

---

## Tech Stack

| Lapisan | Paket | Versi |
|---|---|---|
| Framework | `laravel/framework` | 13.x |
| Jembatan frontend | `inertiajs/inertia-laravel` | 2.x |
| Antarmuka | React + TypeScript | 18.x / 5.x |
| Styling | `tailwindcss` | **3.x** |
| Build | `vite` | 8.x |
| Otorisasi | `spatie/laravel-permission` | 8.x |
| Riwayat aktivitas | `spatie/laravel-activitylog` | 5.x |
| Kontrak tipe | `spatie/laravel-data` + `spatie/laravel-typescript-transformer` | 4.x / 3.x |
| Ekstraksi teks | `smalot/pdfparser`, `phpoffice/phpword` | 2.x / 1.x |
| OCR | Tesseract via `thiagoalessio/tesseract_ocr` | 2.x |
| Pratinjau Office | LibreOffice + Ghostscript | paket sistem |

### Catatan Versi yang Mudah Keliru

Beberapa paket mengubah API antar versi mayor. Contoh kode yang beredar di
internet umumnya masih menulis bentuk lama, dan akan gagal di sini:

| Hal | Bentuk lama (salah di proyek ini) | Bentuk yang benar |
|---|---|---|
| Trait activitylog | `Spatie\Activitylog\Traits\LogsActivity` (v4) | `Spatie\Activitylog\Models\Concerns\LogsActivity` (v5) |
| Konfigurasi TypeScript transformer | `config/typescript-transformer.php` (v2) | Service provider `app/Providers/TypeScriptTransformerServiceProvider.php` (v3) |
| Tailwind | `@tailwindcss/vite` + CSS-first (v4) | `postcss` + `tailwind.config.js` (v3) |
| Timestamp tabel pivot | `->withTimestamps()` | `->withPivot('created_at')` — tabel pivot di sini tidak punya `updated_at` |

### Kontrak Tipe Backend–Frontend

Tipe TypeScript digenerate dari DTO dan enum PHP, tidak ditulis tangan:

```bash
php artisan typescript:transform
```

Hasilnya di `resources/js/types/generated.d.ts` — **jangan disunting manual**,
isinya ditimpa setiap kali perintah di atas dijalankan. Jalankan setiap kali DTO
atau enum berubah, lalu ikutkan hasilnya dalam commit.

---

## Pemecahan Masalah

| Gejala | Sebab yang paling mungkin |
|---|---|
| Status ekstraksi macet di "Memproses" | `php artisan queue:work` tidak berjalan |
| Dokumen tidak pernah berpindah ke Kadaluarsa | `php artisan schedule:work` tidak berjalan |
| OCR gagal untuk semua berkas gambar | Tesseract belum terpasang di sistem operasi |
| Pratinjau Word, Excel, atau PowerPoint gagal | LibreOffice atau Ghostscript belum terpasang, atau worker `thumbnail` tidak berjalan |
| `migrate` gagal saat membuat tabel `documents` | Database mengarah ke SQLite, bukan MySQL/MariaDB — index FULLTEXT tidak didukung |
| Pencarian tidak menemukan kata pendek | Batasan bawaan InnoDB: kata di bawah 3 huruf tidak diindeks FULLTEXT |
| Pratinjau PDF kosong setelah `npm run build` | Berkas worker pdf.js belum tersalin ke direktori publik |
| Tidak bisa masuk sama sekali | Kredensial Superadmin di `.env` salah. Perbaiki `.env`, lalu jalankan `php artisan dms:superadmin` |

---

## Struktur Folder

```text
app/
├── Enums/            Nilai tetap: status dokumen, cakupan edit, status ekstraksi
├── Http/
│   ├── Controllers/  Tipis — logika berada di Services
│   └── Requests/     Validasi masukan, satu per aksi
├── Jobs/             Pekerjaan latar (ekstraksi teks & OCR)
├── Models/           Eloquent
├── Policies/         Otorisasi terpusat
└── Services/         Logika bisnis

resources/js/
├── Components/
│   ├── ui/           Primitif: tombol, masukan, lencana, modal
│   ├── data/         Penyaji data generik: tabel, filter, pagination
│   └── domain/       Komponen khas DMS: pemilih akses, pratinjau dokumen
├── Layouts/          Kerangka halaman
├── Pages/            Satu berkas per rute
├── hooks/            Logika stateful yang dipakai ulang
├── lib/              Fungsi murni: format tanggal, ukuran berkas
└── types/            Tipe TypeScript (sebagian digenerate dari DTO PHP)
```

Aturan lengkap — batas ukuran berkas, arah ketergantungan antar lapisan, dan
aturan performa — ada di dokumen arsitektur frontend milik tim.

---

## Lisensi

Proyek magang internal BPMA. Tidak untuk distribusi publik.
