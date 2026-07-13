# Deploy Production BALIKOS

Domain yang dipakai aplikasi mobile:

```text
https://api.balikos.id
```

Endpoint wajib sebelum Play Store rollout:

```text
GET  https://api.balikos.id/health
GET  https://api.balikos.id/privacy-policy
GET  https://api.balikos.id/account-deletion
POST https://api.balikos.id/api/balikos/login
```

`/api/balikos/login` tidak boleh 404. Untuk kredensial salah, respons normal biasanya `422` atau `401`.

## Server Requirement

- PHP 8.2+
- Composer
- MySQL/MariaDB
- Node.js untuk build asset
- HTTPS aktif
- Web root harus mengarah ke folder `balikos/public`

## File Environment

Salin:

```bash
cp .env.production.example .env
```

Isi minimal:

```text
APP_KEY=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

Generate key:

```bash
php artisan key:generate --force
```

## Build dan Migration

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jika memakai queue database untuk notifikasi/pekerjaan latar:

```bash
php artisan queue:work --tries=3
```

## Nginx Contoh

```nginx
server {
    listen 443 ssl http2;
    server_name api.balikos.id;

    root /var/www/balikos/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Cloudflare/DNS

`api.balikos.id` harus mengarah ke IP origin server yang menjalankan Laravel. Jika sekarang muncul `DEPLOYMENT_NOT_FOUND`, berarti domain masih mengarah ke deployment/hosting yang belum berisi aplikasi BALIKOS atau belum dikaitkan ke project yang benar.

## Smoke Test

Jalankan dari luar server:

```bash
curl -i https://api.balikos.id/health
curl -i https://api.balikos.id/privacy-policy
curl -i https://api.balikos.id/account-deletion
curl -i -X POST https://api.balikos.id/api/balikos/login \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"demo-owner@balikos.id\",\"password\":\"password-demo\",\"device_name\":\"smoke\"}"
```

Setelah endpoint di atas tidak 404, baru lanjut upload/rollout AAB di Play Console.
