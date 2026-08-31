# Referensi environment

Salin `.env.example` untuk pengembangan, atau mulai dari
`.env.production.example` untuk deployment. Jangan commit nilai rahasia.

| Kelompok | Variabel penting | Keterangan |
|---|---|---|
| Aplikasi | `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | `APP_DEBUG` harus nonaktif di production. |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Production memakai akun database non-root. |
| Superadmin awal | `SUPERADMIN_NAME`, `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` | Dipakai oleh `php artisan dms:superadmin`; simpan di secret manager/environment host. |
| Queue/cache | `QUEUE_CONNECTION`, `CACHE_STORE`, `DB_QUEUE_RETRY_AFTER` | Pastikan retry queue lebih besar dari timeout job terpanjang. |
| Penyimpanan | `FILESYSTEM_DISK` | Berkas aplikasi tetap privat dan tidak disajikan langsung dari `public/`. |
| Observabilitas | `LOG_CHANNEL`, `LOG_LEVEL` | Monitoring/backup off-box memerlukan layanan dan kredensial deployment terpisah. |

Untuk test, gunakan konfigurasi `APP_ENV=testing` dengan database
`bpma-dms-ta-testing`; jangan menunjuk ke database development atau production.
