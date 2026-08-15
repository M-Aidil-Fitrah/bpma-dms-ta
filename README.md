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
```

Isi kredensial Superadmin di `.env` — akun ini satu-satunya jalan masuk pertama
ke aplikasi, karena **tidak ada registrasi publik**:

```env
SUPERADMIN_NAME="Administrator BPMA"
SUPERADMIN_EMAIL=superadmin@bpma.internal
SUPERADMIN_PASSWORD=ganti-dengan-kata-sandi-kuat
```

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
php artisan serve          # 1. Server aplikasi
npm run dev                # 2. Server aset frontend
php artisan queue:work     # 3. Ekstraksi teks & OCR asinkron
php artisan schedule:work  # 4. Perpindahan status dokumen ke Kadaluarsa
```

Kalau lupa menjalankan nomor 3 atau 4, aplikasi tetap terbuka dan terlihat
normal — tapi status ekstraksi dokumen akan macet selamanya di "Memproses", dan
dokumen yang masa berlakunya lewat tidak pernah berpindah status. Keduanya
terlihat seperti bug, padahal hanya prosesnya yang belum jalan.

---

## Batas Ukuran Unggahan

Aplikasi menetapkan batas **1 GB**, dan angka itu **berlaku sama di semua
lingkungan** — laptop pengembangan maupun VPS. Batas yang berbeda-beda per mesin
membuat pengujian tidak dapat dipercaya: berkas yang lolos di laptop bisa
ditolak di server tanpa satu pun perubahan kode.

Supaya angka itu benar-benar tercapai, **tiga lapis** harus disetel, dan dua di
antaranya berada di luar kendali kode:

| Lapis | Setelan | Nilai |
|---|---|---|
| PHP | `upload_max_filesize` | `1100M` |
| PHP | `post_max_size` | `1100M` |
| PHP | `memory_limit` | `512M` |
| PHP | `max_execution_time` | `0` (tanpa batas) |
| Nginx (VPS) | `client_max_body_size` | `1100M` |
| Apache (VPS) | `LimitRequestBody` | `1153433600` |

Nilainya sengaja sedikit di atas 1 GB: satu permintaan unggah memuat berkas
**beserta** seluruh medan formulir, token, dan pembungkus multipart.

`memory_limit` tidak perlu sebesar berkasnya — PHP mengalirkan unggahan ke
berkas sementara di disk, bukan menampungnya di memori.

Untuk nginx, `client_max_body_size` saja tidak cukup pada unggahan sebesar ini.
Tambahkan juga:

```nginx
client_max_body_size 1100M;
client_body_timeout  600s;   # unggahan besar butuh waktu
proxy_read_timeout   600s;
fastcgi_read_timeout 600s;
```

Tanpa penambahan tenggat waktu itu, unggahan besar pada koneksi lambat terputus
di tengah jalan dengan galat 504 — kegagalan yang terlihat acak dan sangat sulit
ditelusuri karena bergantung kecepatan jaringan penggunanya.

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
