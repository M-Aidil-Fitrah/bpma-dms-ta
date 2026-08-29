# Kontribusi

## Prinsip perubahan

- Jaga perilaku aplikasi yang sudah stabil. Perubahan kontrak fitur perlu
  persetujuan eksplisit dan test yang mengunci perilaku tersebut.
- Pisahkan commit per topik; gunakan pesan Conventional Commit satu baris yang
  menjelaskan hasilnya, misalnya `test: cover trash purge retention boundaries`.
- Jangan menambahkan trailer atribusi otomatis pada commit.
- Perbarui `scripts/PROGRES-DAN-LANJUTAN.md` untuk pekerjaan audit/lanjutan.

## Pemeriksaan lokal

```bash
./vendor/bin/pint --test
npm run lint
npm run format:check
npm run typecheck
npm test
npm run build
./vendor/bin/phpunit
```

Jalankan PHPUnit satu proses pada satu waktu. Database test yang sah hanya
`bpma-dms-ta-testing`; jangan pernah menjalankan `migrate:fresh` terhadap
database development/production atau database proyek BPMA lain.

## Dokumentasi dan deployment

Perubahan yang memengaruhi operasi harus memperbarui README, deployment guide,
atau referensi environment yang relevan. Jangan menambah Redis, Horizon, kuota
total penyimpanan, atau rate limit upload tanpa keputusan produk eksplisit.
