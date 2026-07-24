# Push Notification BALIKOS

BALIKOS memakai Firebase Cloud Messaging (FCM) untuk aplikasi Android. File
`google-services.json` berada di proyek mobile dan aman disertakan dalam build.
Private key Firebase hanya boleh berada di server backend.

## Deploy Backend

1. Salin private key ke `storage/app/firebase-service-account.json`.
2. Tambahkan env:

   ```env
   FIREBASE_CREDENTIALS=storage/app/firebase-service-account.json
   ```

3. Jalankan:

   ```bash
   php artisan migrate --force
   php artisan config:cache
   ```

4. Pastikan scheduler Laravel dijalankan oleh cron setiap menit:

   ```cron
   * * * * * cd /path/to/balikos && php artisan schedule:run >> /dev/null 2>&1
   ```

Jangan commit atau mengirim `firebase-service-account.json` ke repository.

## Uji

Login ke aplikasi Android hasil build terbaru, izinkan notifikasi, lalu buka
`Lainnya > Bantuan > Uji Notifikasi`. Backend juga mencatat hasil pengiriman di
table `push_notification_deliveries`.
