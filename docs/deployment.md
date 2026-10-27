# Deploy Invitefully ke VPS

Panduan memasang Invitefully di VPS Ubuntu 24.04 dengan Nginx, PHP 8.3-FPM, MySQL 8, Supervisor (queue worker), dan cron (scheduler). Contoh memakai domain `undangan.example.com` dan folder `/var/www/invitefully`; ganti sesuai milik Anda.

## Ringkasan arsitektur

| Komponen | Kegunaan |
| --- | --- |
| Nginx + PHP-FPM | Melayani web (root ke `public/`) |
| MySQL | Database utama, juga dipakai untuk session, cache, dan queue (`database` driver) |
| Queue worker (Supervisor) | Wajib: kirim WhatsApp, impor tamu, proses webhook pembayaran |
| Scheduler (cron) | Wajib: kampanye WhatsApp terjadwal (tiap menit), kedaluwarsa langganan (tiap jam) |
| `storage/app/public` | Upload foto, musik, thumbnail template (diakses lewat `public/storage`) |

Spesifikasi minimum yang disarankan: 2 vCPU, 2 GB RAM, 25 GB SSD.

---

## 1. Siapkan server

Masuk sebagai root, buat user deploy, dan aktifkan firewall:

```bash
adduser deploy
usermod -aG sudo deploy
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw enable
timedatectl set-timezone Asia/Jakarta
```

Pasang paket yang dibutuhkan:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server supervisor unzip git curl

# PHP 8.3 + ekstensi yang dipakai aplikasi
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 22 (Vite 8 butuh Node 20.19+ atau 22.12+)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

Ekstensi penting dan alasannya:

- `gd`: memperkecil dan memotong foto upload (`MediaService`).
- `zip`: ekspor/impor paket template dan impor Excel tamu.
- `intl`: format angka dan tanggal berbahasa Indonesia.
- `mysql`, `mbstring`, `xml`, `curl`, `bcmath`: kebutuhan Laravel dan maatwebsite/excel.

### Batas upload PHP

Upload musik dan paket template bisa sampai 12 MB. Edit `/etc/php/8.3/fpm/php.ini`:

```ini
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 120
```

```bash
sudo systemctl restart php8.3-fpm
```

## 2. Database

```bash
sudo mysql_secure_installation
sudo mysql
```

```sql
CREATE DATABASE invitefully CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'invitefully'@'localhost' IDENTIFIED BY 'GANTI_DENGAN_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON invitefully.* TO 'invitefully'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 3. Unggah kode

Project ini belum memakai git, jadi ada dua pilihan.

**A. Disarankan: pakai git.** Buat repository privat (GitHub/GitLab), push kode, lalu di server:

```bash
sudo mkdir -p /var/www/invitefully && sudo chown deploy:www-data /var/www/invitefully
git clone git@github.com:akun-anda/invitefully.git /var/www/invitefully
```

**B. Salin langsung** dari komputer lokal, tanpa folder yang dibuat ulang di server:

```bash
rsync -avz --exclude vendor --exclude node_modules --exclude .env \
    --exclude 'storage/logs/*' --exclude 'storage/framework/*/*' --exclude public/hot \
    --exclude public/storage \
    ./ deploy@IP_SERVER:/var/www/invitefully/
```

Jangan pernah menyalin `.env` lokal ke server. Buat yang baru di langkah berikutnya.

## 4. Konfigurasi `.env` produksi

```bash
cd /var/www/invitefully
cp .env.example .env
nano .env
```

Nilai yang wajib diubah:

```dotenv
APP_NAME=Invitefully
APP_ENV=production
APP_DEBUG=false
APP_URL=https://undangan.example.com
APP_LOCALE=id

LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=invitefully
DB_USERNAME=invitefully
DB_PASSWORD=GANTI_DENGAN_PASSWORD_KUAT

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database

# Email (wajib: verifikasi email dan reset kata sandi)
MAIL_MAILER=smtp
MAIL_HOST=smtp.provider-anda.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="noreply@undangan.example.com"
MAIL_FROM_NAME="${APP_NAME}"

# Akun super admin pertama (kosongkan lagi setelah seeding)
SEED_ADMIN_EMAIL=admin@undangan.example.com
SEED_ADMIN_PASSWORD=password-sementara-yang-kuat
SEED_DEMO_PASSWORD=
```

Opsional, sesuai fitur yang dipakai:

| Variabel | Catatan |
| --- | --- |
| `PAYMENT_GATEWAY` | `manual` (default), `xendit`, atau `tripay` |
| `XENDIT_SECRET_KEY`, `XENDIT_CALLBACK_TOKEN` | Webhook: `https://undangan.example.com/webhooks/payments/xendit` |
| `TRIPAY_API_KEY`, `TRIPAY_PRIVATE_KEY`, `TRIPAY_MERCHANT_CODE`, `TRIPAY_SANDBOX=false` | Callback: `https://undangan.example.com/webhooks/payments/tripay` |
| `PAYMENT_MANUAL_INSTRUCTIONS` | Teks rekening untuk transfer manual |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Redirect URI: `https://undangan.example.com/auth/google/callback` |
| `WHATSAPP_FONNTE_TUTORIAL_URL` | Video tutorial di halaman "Nomor WhatsApp saya" |
| `APP_LOGO` | Path logo relatif terhadap `public/` |

Kredensial provider WhatsApp (Fonnte, Wablas, Meta, Twilio) tidak disimpan di `.env`, tetapi diatur dari `/admin/whatsapp`. URL webhook-nya: `https://undangan.example.com/webhooks/whatsapp/{driver}`.

## 5. Instal dependensi, build, dan migrasi

```bash
cd /var/www/invitefully
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm ci
npm run build
rm -rf node_modules          # opsional, hemat disk; jalankan npm ci lagi saat update

php artisan migrate --force
php artisan storage:link
```

### Data awal (seeding)

Jangan jalankan `php artisan db:seed` begitu saja di produksi, karena akan ikut membuat data demo. Jalankan seeder yang dibutuhkan satu per satu:

```bash
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=SystemSettingSeeder --force
php artisan db:seed --class=UserSeeder --force          # hanya super admin (SEED_DEMO_PASSWORD kosong)
php artisan db:seed --class=PlanSeeder --force
php artisan db:seed --class=TemplateCategorySeeder --force
php artisan db:seed --class=InvitationTemplateSeeder --force
php artisan db:seed --class=IllustrationSeeder --force
php artisan db:seed --class=PageSeeder --force          # Syarat & Ketentuan + Kebijakan Privasi (draft)
```

Setelah itu, kosongkan `SEED_ADMIN_PASSWORD` di `.env`, login, lalu ganti kata sandi admin dari halaman Pengaturan.

`PageSeeder` membuat halaman hukum berstatus Draft. Tinjau isinya di `/admin/content` sebelum dipublikasikan.

### Hak akses folder

```bash
sudo chown -R deploy:www-data /var/www/invitefully
sudo find /var/www/invitefully -type d -exec chmod 755 {} \;
sudo find /var/www/invitefully -type f -exec chmod 644 {} \;
sudo chmod -R ug+rwx /var/www/invitefully/storage /var/www/invitefully/bootstrap/cache
chmod 640 /var/www/invitefully/.env
```

### Cache konfigurasi

```bash
php artisan optimize
```

Perintah ini menyimpan cache config, route, event, dan view. Jalankan ulang setiap kali `.env` atau kode berubah.

## 6. Nginx

Buat `/etc/nginx/sites-available/invitefully`:

```nginx
server {
    listen 80;
    server_name undangan.example.com;
    root /var/www/invitefully/public;
    index index.php;

    client_max_body_size 25M;
    charset utf-8;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~* \.(css|js|svg|png|jpg|jpeg|webp|woff2?|mp3|m4a)$ {
        expires 30d;
        access_log off;
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

`client_max_body_size` harus sama atau lebih besar dari `post_max_size` PHP. Kalau tidak, upload musik akan gagal dengan error 413.

```bash
sudo ln -s /etc/nginx/sites-available/invitefully /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

### HTTPS (Let's Encrypt)

Arahkan DNS record A domain ke IP VPS terlebih dahulu, lalu:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d undangan.example.com
```

Certbot memperbarui sertifikat otomatis. HTTPS wajib untuk login Google, webhook pembayaran, dan `SESSION_SECURE_COOKIE=true`.

## 7. Queue worker (Supervisor)

Tanpa worker, pesan WhatsApp, impor tamu, dan webhook pembayaran hanya akan tertahan di tabel `jobs`.

Buat `/etc/supervisor/conf.d/invitefully-worker.conf`:

```ini
[program:invitefully-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/invitefully/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
user=deploy
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/invitefully/storage/logs/worker.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Kalau `WHATSAPP_QUEUE` diisi selain `default`, tambahkan antreannya ke perintah worker, misalnya `--queue=default,whatsapp`.

## 8. Scheduler (cron)

```bash
sudo crontab -u deploy -e
```

Tambahkan satu baris:

```cron
* * * * * cd /var/www/invitefully && php artisan schedule:run >> /dev/null 2>&1
```

Cron ini menjalankan `whatsapp:dispatch-scheduled` (tiap menit) dan `subscriptions:process-expirations` (tiap jam). Cek dengan `php artisan schedule:list`.

## 9. Setelah online

1. Buka `https://undangan.example.com` dan pastikan halaman utama tampil dengan CSS.
2. Login sebagai super admin di `/login`.
3. Di `/admin/settings`, isi nama situs, email support, zona waktu default, dan status pendaftaran, lalu unggah ikon situs (PNG persegi minimal 512 × 512 px).
4. Di `/admin/content`, sesuaikan konten landing page dan publikasikan halaman hukum.
5. Di `/admin/plans`, periksa harga paket.
6. Di `/admin/whatsapp`, tambahkan provider WhatsApp platform, lalu kirim pesan uji.
7. Daftarkan URL webhook di dashboard Xendit/Tripay dan provider WhatsApp (lihat langkah 4).
8. Uji alur penting:
   - daftar akun baru dan cek email verifikasi masuk;
   - buat undangan dan publikasikan;
   - kirim RSVP dari halaman publik;
   - kirim kampanye WhatsApp dan pastikan statusnya berubah (tanda worker berjalan).

## 10. Update aplikasi

```bash
cd /var/www/invitefully
php artisan down --refresh=15

git pull                    # atau rsync ulang seperti langkah 3B
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize

php artisan queue:restart   # worker memuat kode baru setelah job berjalan selesai
php artisan up
```

`php artisan queue:restart` wajib dijalankan. Tanpanya, worker tetap menjalankan kode lama.

## 11. Backup

Yang perlu dibackup: database dan `storage/app` (semua upload). Kode bisa diambil ulang dari repository.

Contoh skrip `/home/deploy/backup.sh`:

```bash
#!/bin/bash
set -e
DATE=$(date +%F)
DIR=/home/deploy/backups
mkdir -p "$DIR"

mysqldump --single-transaction -u invitefully -p'GANTI_DENGAN_PASSWORD_KUAT' invitefully | gzip > "$DIR/db-$DATE.sql.gz"
tar -czf "$DIR/storage-$DATE.tar.gz" -C /var/www/invitefully storage/app

find "$DIR" -type f -mtime +14 -delete
```

```bash
chmod 700 /home/deploy/backup.sh
crontab -e
# 0 2 * * * /home/deploy/backup.sh
```

Simpan salinan backup di luar VPS juga, misalnya object storage atau komputer lain.

## 12. Pemecahan masalah

| Gejala | Penyebab umum | Solusi |
| --- | --- | --- |
| Error 500 tanpa detail | Izin folder atau `.env` salah | Lihat `storage/logs/laravel-*.log`; ulangi langkah hak akses |
| Halaman tanpa CSS/JS | Belum `npm run build` atau ada file `public/hot` | Build ulang; hapus `public/hot` |
| Foto/musik upload tidak tampil | Belum `storage:link` atau `APP_URL` salah | `php artisan storage:link`; samakan `APP_URL` dengan domain HTTPS |
| Upload gagal 413 | `client_max_body_size` terlalu kecil | Naikkan di Nginx, lalu `reload` |
| Pesan WhatsApp tidak terkirim | Worker mati | `sudo supervisorctl status`; cek `storage/logs/worker.log` |
| Kampanye terjadwal tidak jalan | Cron belum dipasang | Cek `crontab -u deploy -l` |
| Email verifikasi tidak masuk | `MAIL_MAILER=log` atau SMTP salah | Isi SMTP yang benar, lalu `php artisan optimize` |
| Login Google "redirect_uri_mismatch" | URI di Google Console tidak sama persis | Daftarkan `https://domain/auth/google/callback` |
| Perubahan `.env` tidak berpengaruh | Config masih di-cache | `php artisan optimize` |
| Error 419 (Page Expired) | Domain/HTTPS tidak cocok dengan session | Pastikan `APP_URL` HTTPS dan `SESSION_DOMAIN=null` |

## Checklist keamanan

- [ ] `APP_ENV=production` dan `APP_DEBUG=false`
- [ ] `.env` tidak bisa diakses publik (root Nginx ke `public/`) dan berizin `640`
- [ ] HTTPS aktif, `SESSION_SECURE_COOKIE=true`
- [ ] `SEED_ADMIN_PASSWORD` dikosongkan dan kata sandi admin sudah diganti
- [ ] Data demo tidak di-seed
- [ ] `TRIPAY_SANDBOX=false` saat sudah memakai pembayaran sungguhan
- [ ] Firewall hanya membuka SSH, 80, dan 443; MySQL tidak terbuka ke internet
- [ ] Login SSH memakai key, login root dimatikan
- [ ] Backup harian berjalan dan pernah diuji restore
