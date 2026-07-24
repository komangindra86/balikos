# BALIKOS Mobile Expo

Versi mobile baru berbasis Expo React Native, mengikuti stack dan pola UI `undangan-bali/mobile`.

## Jalankan

```bash
cd C:\laragon\www\balikos-mobile
npm install
npm run android
```

Default API emulator:

```text
http://10.0.2.2/api/balikos
```

Fitur yang sudah dipoles:

- Login dan daftar pemilik kos.
- Dashboard ringkas.
- Pilih kos aktif.
- Daftar kamar dengan kartu yang bisa diklik.
- Detail kamar dengan penghuni aktif jika kamar terisi.
- Tambah kamar dengan label jelas, status picker, switch fasilitas, catatan, dan upload foto kamar.

## Push Notification Android

Konfigurasi publik Firebase ada di `google-services.json`. Setelah mengubah
plugin native, sinkronkan folder Android dan build ulang:

```bash
npx expo prebuild --platform android --no-install
cd android
gradlew.bat app:bundleRelease
```

Remote push tidak dapat diuji lewat Expo Go. Gunakan APK/AAB native, izinkan
notifikasi, lalu pilih `Lainnya > Bantuan > Uji Notifikasi`.
