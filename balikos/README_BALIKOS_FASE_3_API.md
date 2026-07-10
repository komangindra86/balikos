# BALIKOS - Fase 3 REST API

API ini dipakai aplikasi Android native pemilik kos. Semua endpoint memakai Query Builder dan token Bearer custom dari tabel `api_tokens`.

## Auth

Login hanya untuk user role `pemilik_kos`. Pendaftaran pemilik kos juga tersedia dari Android.

```http
POST /api/balikos/login
Content-Type: application/json

{
  "email": "pemilik@balikos.test",
  "password": "password",
  "device_name": "android"
}
```

Gunakan token dari response:

```http
Authorization: Bearer {token}
Accept: application/json
```

Endpoint auth:

- `POST /api/balikos/login`
- `POST /api/balikos/register`
- `POST /api/balikos/logout`
- `GET /api/balikos/me`

## Endpoint Pemilik Kos

- `GET /api/balikos/dashboard`
- `GET|POST /api/balikos/kos`
- `GET|PUT|DELETE /api/balikos/kos/{id}`
- `GET|POST /api/balikos/kamar?kos_id=`
- `GET|PUT|DELETE /api/balikos/kamar/{id}`
- `GET|POST /api/balikos/penghuni?kos_id=`
- `GET|PUT|DELETE /api/balikos/penghuni/{id}`
- `GET /api/balikos/penghuni/{id}/portal-link`
- `GET /api/balikos/tagihan?kos_id=&bulan=&tahun=&status=`
- `POST /api/balikos/tagihan/generate` dengan `kamar_id` opsional untuk generate per kamar
- `POST /api/balikos/tagihan/auto-generate`
- `GET /api/balikos/tagihan/{id}`
- `PUT /api/balikos/tagihan/{id}/lunas`
- `PUT /api/balikos/tagihan/{id}/verifikasi`
- `PUT /api/balikos/tagihan/{id}/tolak`
- `GET|POST /api/balikos/payment-methods?kos_id=`
- `PUT|DELETE /api/balikos/payment-methods/{id}`
- `GET|POST /api/balikos/keuangan?kos_id=&bulan=&tahun=`
- `PUT|DELETE /api/balikos/keuangan/{id}`
- `GET|POST /api/balikos/pengumuman?kos_id=`
- `PUT|DELETE /api/balikos/pengumuman/{id}`

## Keamanan

- Semua endpoint selain login wajib memakai Bearer token.
- Token disimpan sebagai SHA-256 hash, bukan plain text.
- API hanya menerima role `pemilik_kos`.
- Semua query data kos/kamar/penghuni/tagihan/pembayaran/keuangan/pengumuman difilter berdasarkan kos milik user login.
- Upload dibatasi gambar dengan ukuran maksimum 2 MB untuk foto kamar dan QRIS.

## Auto-generate Tagihan Lokal

Jalankan manual:

```bash
php artisan balikos:auto-generate-tagihan --days=7
```

Atau double-click:

```text
C:\laragon\www\balikos\AUTO-GENERATE-TAGIHAN.bat
```

Logika saat ini: tagihan dibuat ketika tanggal jatuh tempo penghuni berada `--days` hari dari hari ini. Hari jatuh tempo mengikuti hari pada `tanggal_masuk` penghuni, dibatasi maksimal tanggal 28 agar aman untuk semua bulan.
