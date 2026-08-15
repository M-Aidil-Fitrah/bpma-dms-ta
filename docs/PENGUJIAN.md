# Hasil Pengujian Otomatis

Dokumen ini mencatat bukti pengujian untuk penyerahan proyek. Fokusnya adalah
perilaku yang berisiko mahal bila rusak: otorisasi, validasi, siklus ekstraksi,
penyajian berkas, dan pencarian dengan batas akses.

## Lingkungan

- Tanggal verifikasi: 16 Agustus 2026
- Perintah: `php artisan test`
- Database: MySQL/MariaDB `bpma-dms-testing`, sebagaimana dikunci oleh
  `phpunit.xml`. SQLite tidak dipakai karena indeks FULLTEXT dokumen perlu
  diverifikasi pada mesin database yang dipakai aplikasi.
- Penyimpanan berkas: disk `local` palsu per tes; berkas pengembangan tidak
  disentuh.

## Cakupan Prioritas

| Prioritas | Bukti utama |
|---|---|
| Matriks otorisasi | `DocumentAccessTest`, `DemoAuthorizationMatrixTest`, `DocumentShowTest` |
| Validasi dokumen | `DocumentUploadTest`, `DocumentUpdateTest`, `KeamananDokumenTest` |
| Siklus ekstraksi | `DocumentUploadTest`, `ExtractDocumentTextJobTest`, `DocumentTextExtractorTest` |
| Keamanan berkas | `KeamananDokumenTest` — unduh dan pratinjau memakai policy yang sama, termasuk berkas yang dapat memuat skrip |
| Pencarian terbatas akses | `DocumentFulltextSearchTest`, `DocumentIndexTest` |

`DemoAuthorizationMatrixTest` memakai data seed sungguhan. Dokumen *Laporan
Evaluasi Proyek X* harus dapat dibuka oleh Fitri melalui unit, Hasan melalui
jenjang jabatan, dan Maya melalui berbagi langsung; Rizki sebagai kontrol
negatif menerima 403 pada detail, unduhan, serta pratinjau.

## Hasil Verifikasi

Semua kelompok berikut dijalankan pada database pengujian yang terpisah.

| Kelompok | Tes | Asersi | Hasil |
|---|---:|---:|---|
| Aktivitas, autentikasi, dasbor, matriks demo, dan policy akses | 65 | 348 | Lulus |
| Detail, indeks, FULLTEXT, relasi, seed dokumen, ubah metadata, dan ekstraksi teks | 65 | 412 | Lulus |
| Unggah, job ekstraksi, dan keamanan berkas | 60 | 177 | Lulus |
| Organisasi, pengaturan, profil, dan rute dasar | 24 | 168 | Lulus |
| Seed referensi, provisioning Superadmin, dan transisi status otomatis | 20 | 85 | Lulus |
| Manajemen pengguna dan unit test | 25 | 110 | Lulus |
| **Total** | **259** | **1.300** | **Lulus** |

## Temuan yang Ditutup

- Fallback pratinjau untuk tipe berkas yang tidak aman ditampilkan inline
  sebelumnya tidak meneruskan `Request` ke jalur unduh dan menghasilkan 500.
  Jalur tersebut kini memakai otorisasi serta pencatatan unduhan yang sama.
- Anggaran query dasbor diperbarui menjadi 15 query yang tetap: dua query
  tambahan berasal dari setelan aplikasi dan riwayat aktivitas, bukan dari
  pertumbuhan jumlah dokumen.
