# Deployment BPMA DMS

Dokumen ini menjelaskan rilis produksi berbasis Docker Compose dan alternatif
Supervisor untuk host yang tidak memakai Docker. Aplikasi membutuhkan MySQL,
Tesseract bahasa Indonesia, LibreOffice, Ghostscript, dan Poppler; image proyek
memasang seluruh binari tersebut.

Artefak ini menjalankan antrean melalui driver **database** pada MySQL. Redis,
backup off-box, dan pemeriksaan health aplikasi yang mendalam belum dicakup
oleh rilis ini; jangan menyatakan ketiganya telah aktif hanya karena kontainer
berjalan. Endpoint `/up` hanya menunjukkan aplikasi Laravel dapat melakukan
boot, bukan kesiapan seluruh dependensi.

## Prasyarat dan secret

- Docker Engine 25+ dan Docker Compose v2 untuk jalur container.
- Nama DNS/TLS terminator di depan port aplikasi untuk deployment Internet.
- Secret manager atau berkas `.env` yang hak aksesnya dibatasi (`chmod 600`).
- Akun database aplikasi non-root dan kata sandi Superadmin unik minimal 16
  karakter.

Jangan menyalin `.env.example` untuk produksi. Mulai dari template produksi,
lalu isi seluruh placeholder dan tambahkan secret root MySQL yang hanya
digunakan oleh Compose:

```bash
cp .env.production.example .env
chmod 600 .env
```

Minimal sesuaikan nilai berikut di `.env`:

```dotenv
APP_KEY=base64:GENERATE_DENGAN_php_artisan_key:generate
APP_URL=https://dms.example.go.id
APP_DEBUG=false

DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=bpma-dms
DB_USERNAME=bpma_dms
DB_PASSWORD=ganti-dengan-secret-aplikasi
MYSQL_ROOT_PASSWORD=ganti-dengan-secret-root-khusus-compose

SUPERADMIN_EMAIL=admin@example.go.id
SUPERADMIN_PASSWORD=ganti-dengan-secret-superadmin-minimal-16-karakter
```

Gunakan `php artisan key:generate` sebelum kontainer aplikasi dijalankan, atau
hasilkan `APP_KEY` melalui mekanisme secret manager yang setara. Jangan gunakan
`DB_USERNAME=root`; Compose membuat akun `DB_USERNAME`/`DB_PASSWORD` untuk
database `DB_DATABASE` ketika volume MySQL masih baru.

## Rilis dengan Docker Compose

1. Siapkan `.env` seperti di atas. Pastikan `APP_ENV=production`,
   `APP_DEBUG=false`, `SESSION_ENCRYPT=true`, `QUEUE_CONNECTION=database`,
   `CACHE_STORE=database`, dan `DB_QUEUE_RETRY_AFTER=1200` tetap terisi.
2. Bangun image dan mulai database serta inisialisasi volume dokumen:

   ```bash
   docker compose build --pull
   docker compose up -d mysql init-storage
   ```

3. Terapkan skema **sekali untuk rilis ini**, setelah backup database yang
   telah tersedia di lingkungan Anda diverifikasi:

   ```bash
   docker compose run --rm app php artisan migrate --force
   docker compose run --rm app php artisan storage:link --force
   ```

4. Mulai proses aplikasi. Jangan menambah scheduler kedua atau menjalankan
   crontab `schedule:run` di host yang sama; stack ini sudah memiliki tepat satu
   service `scheduler`.

   ```bash
   docker compose up -d --remove-orphans
   docker compose ps
   ```

`app`, `queue-default`, `queue-thumbnail`, dan `scheduler` berbagi volume
`app-storage` pada `storage/app`. Karena itu berkas asli, thumbnail, dan PDF
pratinjau yang dibuat worker dapat dibaca oleh aplikasi web. Database MySQL
disimpan terpisah dalam volume `mysql-data`.

Setiap worker dipisahkan menurut queue: `queue-default` untuk ekstraksi/OCR dan
`queue-thumbnail` untuk thumbnail/pratinjau Office. Keduanya didaur ulang setiap
jam, sementara `stopwaitsecs` pada konfigurasi Supervisor melebihi timeout OCR.

### Verifikasi pasca-rilis

```bash
docker compose ps
docker compose logs --tail=100 app queue-default queue-thumbnail scheduler
curl -fsS http://localhost:${APP_PORT:-80}/up
docker compose exec app php artisan about
```

Lakukan juga verifikasi fungsional: masuk sebagai Superadmin, unggah satu PDF
berteks dan satu dokumen Office, pastikan status OCR serta thumbnail/pratinjau
selesai, lalu pastikan berkas asli masih dapat diunduh. Cek bahwa satu scheduler
saja yang berjalan dengan `docker compose ps scheduler`.

## Rilis non-Docker dengan Supervisor

Gunakan metode ini hanya bila host sudah memasang PHP-FPM 8.3, Nginx, MySQL,
Tesseract beserta `ind`, LibreOffice, Ghostscript, dan Poppler. Salin
`deploy/supervisor.conf` ke `/etc/supervisor/conf.d/bpma-dms.conf`, lalu sesuaikan
path `/var/www/bpma-dms/current` dan command `php-fpm8.3` untuk distribusi host.
Nginx dapat memakai `deploy/nginx.conf` sebagai vhost dengan `fastcgi_pass`
diubah ke socket atau alamat PHP-FPM host.

Untuk satu release berbasis direktori versi:

```bash
cd /var/www/bpma-dms/releases/20260829-1
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan storage:link --force
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
ln -sfn /var/www/bpma-dms/releases/20260829-1 /var/www/bpma-dms/current
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart bpma-fpm bpma-queue-default bpma-queue-thumbnail bpma-scheduler
```

Pilih **satu** mekanisme scheduler: `bpma-scheduler` di Supervisor *atau*
crontab `* * * * * php artisan schedule:run`; jangan jalankan keduanya. Konfigurasi
yang disediakan memakai `schedule:work`, jadi crontab tidak diperlukan.

## Rollback

Rollback kode tidak otomatis membalik skema database. Bila rilis baru gagal:

1. Aktifkan maintenance mode bila dampaknya ke pengguna perlu dibatasi.
2. Arahkan `current` (non-Docker) atau tag image Compose ke rilis sebelumnya.
3. Restart PHP-FPM, kedua worker, dan scheduler.
4. Jalankan `php artisan migrate:rollback --step=1 --force` **hanya** setelah
   memastikan migrasi terakhir memiliki `down()` yang benar dan data baru tidak
   akan hilang. Jika tidak, lakukan forward-fix atau pemulihan dari backup yang
   sudah diuji.
5. Verifikasi ulang login, unggah, OCR, thumbnail, dan scheduler; kemudian keluar
   dari maintenance mode jika digunakan.

Jangan menghapus volume `app-storage` atau `mysql-data` saat rollback. Keduanya
menyimpan dokumen dan database yang harus dipertahankan.
