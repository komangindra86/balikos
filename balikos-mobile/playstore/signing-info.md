# BALIKOS Signing Info

Gunakan informasi ini untuk Google OAuth Android Client dan catatan Play Console.

- Package name: `id.balisantih.balikos`
- Current Android versionCode: `2`
- Upload key alias: `balikos-upload`
- Upload key SHA-1:

```text
5F:CC:5A:3A:F9:5B:F4:56:E9:E0:39:6F:AA:50:93:6B:84:C8:C7:9F
```

- Upload key SHA-256:

```text
93:65:CF:2D:7C:60:C8:94:57:05:ED:FC:4C:4F:AE:84:0F:AE:39:37:AA:50:64:C7:7B:F5:82:76:14:03:A0:3B
```

File rahasia yang harus dibackup dan jangan di-push ke GitHub:

```text
C:\laragon\www\balikos-mobile\credentials\balikos-upload-key.jks
C:\laragon\www\balikos-mobile\credentials\balikos-upload-key-info.txt
```

Catatan penting:

- Untuk upload pertama ke Play Store, AAB dapat memakai upload key ini.
- Setelah Play App Signing aktif, Google akan memberi "App signing key certificate" sendiri.
- Untuk Google Login setelah rilis via Play Store, OAuth Android biasanya perlu SHA-1 dari certificate yang dipakai Google Play/App Signing juga. Jika login Google gagal pada versi Play Store, tambahkan SHA-1 Play App Signing dari Play Console ke Google Cloud OAuth Client.
