# BALIKOS - Fase 1

Fase ini membangun fondasi backend web untuk module BALIKOS di platform Bali Santih.

## Ruang Lingkup

- Laravel 12, Blade, Tailwind CSS, MySQL.
- Auth web sederhana berbasis session.
- Login dan logout.
- Middleware auth dan role.
- Layout dashboard dengan sidebar.
- Flash message sukses/gagal.
- Validasi form login.
- Migration database BALIKOS.
- Seeder awal.
- Query Builder untuk seluruh akses data aplikasi.
- Tidak menggunakan Eloquent Model.
- Filter `owner_id` untuk data pemilik kos.

## Akun Seeder

Semua akun memakai password: `password`

- Superadmin: `superadmin@balisantih.test`
- Admin BALIKOS: `admin@balikos.test`
- Pemilik kos: `pemilik@balikos.test`

## Menjalankan

Pastikan runtime memakai PHP 8.2 atau lebih baru, Composer, Node.js, dan MySQL.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Atur koneksi MySQL di `.env`.

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=balikos
DB_USERNAME=root
DB_PASSWORD=
```

Jalankan migration dan seeder:

```bash
php artisan migrate:fresh --seed
```

Jalankan aplikasi:

```bash
npm run dev
php artisan serve
```

Buka `/login`, lalu masuk memakai salah satu akun seeder.

## URL Fase 1

- `/dashboard`
- `/dashboard/balikos`
- `/dashboard/balikos/pemilik`
- `/dashboard/balikos/kos`
- `/dashboard/balikos/indeks-harga`

## Catatan Keamanan Data

- Middleware `balikos.auth` mengambil user aktif dari tabel `users` dengan Query Builder.
- Middleware `balikos.role` membatasi halaman platform untuk `superadmin` dan `admin_balikos`.
- Query kos untuk role `pemilik_kos` selalu dibatasi dengan `kos.owner_id = user.id`.
- Tabel `penghunis` memakai kolom `active_kamar_id` yang unik agar satu kamar tidak dapat memiliki lebih dari satu penghuni aktif. Saat CRUD penghuni dibuat pada fase berikutnya, isi kolom ini dengan `kamar_id` untuk penghuni aktif dan `null` untuk penghuni keluar.
