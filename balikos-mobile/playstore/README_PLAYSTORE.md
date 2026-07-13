# BALIKOS - Paket Persiapan Google Play

Folder ini berisi teks dan checklist yang bisa dipakai saat membuat listing aplikasi BALIKOS di Google Play Console.

## Identitas Aplikasi

- App name: BALIKOS
- Package name: `id.balisantih.balikos`
- Version: `1.0.0`
- Android versionCode: `1`
- Kategori: Business
- Default language: Indonesian (id-ID)
- App icon: `assets/playstore-icon.png` (512x512)

## URL Penting

- API production: `https://api.balikos.id/api/balikos`
- Portal penghuni: `https://api.balikos.id/balikos/portal/{token}`
- Privacy policy: `https://api.balikos.id/privacy-policy`
- Account deletion: `https://api.balikos.id/account-deletion`

Pastikan domain `https://api.balikos.id` sudah live, HTTPS valid, dan endpoint API tidak 404 sebelum upload final.

## File AAB

Untuk build AAB production dengan EAS:

```bash
cd C:\laragon\www\balikos-mobile
npx eas-cli login
npx eas-cli build -p android --profile production
```

Jika belum pernah setup credentials:

```bash
npx eas-cli build:configure
npx eas-cli credentials
```

Setelah build selesai, unduh `.aab` dari link EAS dan upload ke Play Console.

## Yang Masih Perlu Diisi Manual

- Google OAuth Android Client ID dan Web Client ID di `app.json`.
- SHA-1 production dari EAS/Play App Signing.
- Email dukungan resmi untuk Play Console.
- Nomor telepon/website developer jika diminta Play Console.
- Screenshot aplikasi dari perangkat Android.
- Demo account untuk reviewer Google Play.
