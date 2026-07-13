# Pre-launch Checklist BALIKOS

## Wajib Sebelum Upload AAB

- [ ] `https://api.balikos.id` sudah live dengan HTTPS valid.
- [ ] `POST https://api.balikos.id/api/balikos/login` tidak 404.
- [ ] `https://balikos.balisantih.com/privacy-policy` bisa dibuka publik.
- [ ] `https://balikos.balisantih.com/account-deletion` bisa dibuka publik.
- [ ] Google OAuth Client ID sudah diisi jika tombol Google Login ingin ditampilkan.
- [ ] Demo account reviewer sudah dibuat di production.
- [ ] Screenshot Play Store sudah dibuat dari device/emulator.
- [ ] AAB production sudah berhasil dibuat dengan EAS.
- [ ] SHA-1 production sudah dipakai untuk OAuth Android Client.

## Smoke Test Production

- [ ] Login email/password.
- [ ] Tambah kos.
- [ ] Tambah kamar.
- [ ] Upload foto kamar.
- [ ] Tambah penghuni.
- [ ] Upload KTP.
- [ ] Generate tagihan.
- [ ] Share portal penghuni.
- [ ] Upload bukti pembayaran dari portal.
- [ ] Verifikasi pembayaran.
- [ ] Verifikasi ulang QRIS tidak menggandakan saldo wallet.
- [ ] Ajukan tarik saldo QRIS.

## Command Validasi Lokal

```bash
cd C:\laragon\www\balikos-mobile
npx expo-doctor
npm audit --omit=dev
npx expo export --platform web --output-dir dist-check
```
