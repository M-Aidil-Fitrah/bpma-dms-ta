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

Laravel 13 · Inertia.js 2 · React 18 + TypeScript · Tailwind CSS 3 ·
MySQL/MariaDB · spatie/laravel-permission · spatie/laravel-activitylog ·
smalot/pdfparser · phpoffice/phpword · Tesseract OCR

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
| Tidak bisa masuk sama sekali | Kredensial Superadmin di `.env` salah. Jalur darurat: `php artisan tinker` untuk menyetel ulang kata sandi |

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
