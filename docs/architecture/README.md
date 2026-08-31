# Arsitektur BPMA DMS

BPMA DMS adalah prototype internal untuk pengelolaan arsip digital. Data seed
dan akun yang disediakan hanya untuk demo; jangan memasukkan dokumen resmi,
rahasia, atau data pegawai nyata.

## Komponen

```text
Browser
  └─ Inertia + React + TypeScript
       └─ Laravel (policy, request validation, service domain)
            ├─ MySQL/MariaDB (metadata, akses, aktivitas, job database)
            ├─ storage/app/private (berkas asli, thumbnail, PDF pratinjau)
            ├─ queue default (ekstraksi teks dan OCR)
            └─ queue thumbnail (thumbnail dan pratinjau Office)
```

- Controller hanya menangani HTTP dan otorisasi; aturan bisnis berada pada
  service serta policy yang dipakai bersama.
- `DocumentPolicy` menjadi gerbang tunggal akses dokumen. Unduh dan pratinjau
  menjalani policy yang sama dengan halaman detail.
- `ActivityLogService` adalah satu pintu untuk jejak audit aplikasi. Aksi
  scheduler/queue direkam sebagai Sistem (`causer_id` kosong).
- File tidak disajikan langsung dari direktori publik. Controller membentuk
  respons privat beserta header penyajian yang sesuai.

## Alur unggah dan proses latar

1. `StoreDocumentRequest` memvalidasi metadata, akses, dan batas ukuran per
   berkas.
2. `DocumentUploadService` menyimpan berkas dan metadata dalam alur yang
   dibersihkan kembali bila transaksi gagal.
3. Job ekstraksi berjalan di queue `default`; job thumbnail/pratinjau berjalan
   di queue `thumbnail`.
4. Status OCR/preview dipublikasikan kembali melalui data halaman, bukan dengan
   menganggap proses latar pasti sudah selesai pada respons unggah.

## Operasional

- Scheduler menjalankan status kedaluwarsa, purge Sampah, dan retensi aktivitas.
- Batas upload adalah batas **per berkas**. Nilai operasional dapat diatur
  Superadmin, tetapi tidak melampaui batas infrastruktur 2 GB.
- Redis dan Horizon bukan bagian dari scope repository ini. Queue deployment
  menggunakan konfigurasi yang sudah tersedia di lingkungan aplikasi.

## Verifikasi

Gunakan database testing terpisah dan jalankan PHPUnit secara serial. Database
yang sah untuk test adalah `bpma-dms-ta-testing`; jangan pernah menjalankan
`migrate:fresh` terhadap database development, production, atau proyek BPMA lain.
