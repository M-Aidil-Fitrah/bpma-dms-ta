# ADR 0001: Isolasi database testing

Status: Diterima

## Konteks

Test feature memakai MySQL/MariaDB, migrasi, dan index FULLTEXT sehingga tidak
dapat digantikan SQLite. Menjalankan reset skema terhadap database development
atau production berisiko menghapus data yang tidak dapat dipulihkan.

## Keputusan

Semua test lokal memakai database bernama `bpma-dms-ta-testing`. Test dijalankan
serial; jangan menjalankan dua proses PHPUnit yang melakukan reset skema pada
waktu yang sama.

Jika test database perlu dipulihkan, targetnya hanya database tersebut:

```bash
APP_ENV=testing DB_DATABASE=bpma-dms-ta-testing \
  php artisan migrate:fresh --seed --force --no-ansi
```

Perintah tersebut tidak boleh diarahkan ke database development atau production.

## Konsekuensi

- Kegagalan test akibat migrasi/lock dapat ditelusuri tanpa menyentuh data
  aplikasi.
- Pipeline dan developer perlu memastikan satu reset skema aktif pada satu
  waktu.
- Nama database testing berbeda dari proyek BPMA lain agar recovery tidak
  mengganggu checkout lain.
