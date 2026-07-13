# Data Safety - Draft Jawaban Play Console

Catatan: sesuaikan kembali dengan implementasi production final, payment gateway, analytics, dan layanan pihak ketiga yang benar-benar dipakai.

## Apakah aplikasi mengumpulkan atau membagikan data pengguna?

Ya, aplikasi mengumpulkan data pengguna untuk menjalankan fitur pengelolaan kos.

## Apakah semua data dikirim dengan enkripsi?

Ya. Production harus memakai HTTPS.

## Apakah pengguna bisa meminta data dihapus?

Ya. Gunakan URL:

`https://api.balikos.id/account-deletion`

## Data yang Dikumpulkan

### Personal info

- Name
- Email address
- Phone number
- User IDs
- Address

Tujuan: account management, app functionality, communication.

### Financial info

- Purchase history/payment history
- Bank account info untuk penarikan saldo QRIS jika pemilik mengajukan penarikan

Tujuan: payment processing, app functionality, accounting.

### Photos and videos

- Foto kamar
- Foto KTP penghuni jika diunggah
- Foto bukti pembayaran

Tujuan: app functionality, verification.

### Files and docs

- Dokumen/gambar bukti pembayaran dan identitas yang diunggah pengguna

Tujuan: app functionality, verification.

### App activity

- App interactions, seperti aksi membuat kamar, tagihan, pembayaran, pengumuman, dan transaksi

Tujuan: app functionality, security/fraud prevention.

### Device or other IDs

- Push notification token/device identifier

Tujuan: notifications, app functionality.

## Data Sharing

Data dapat diproses oleh penyedia layanan untuk hosting, storage, autentikasi, notifikasi, dan payment gateway. Jangan tandai sebagai "sold". Jika Play Console menanyakan sharing, jawab sesuai vendor production yang benar-benar digunakan.

## Sensitive Permissions

Kemungkinan permission:

- Notifications: untuk notifikasi tagihan/pembayaran.
- Photos/media: untuk upload foto kamar, KTP, dan bukti pembayaran.

Tidak menggunakan SMS atau Call Log.
