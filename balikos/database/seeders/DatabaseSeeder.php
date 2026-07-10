<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = now();

            $superadminId = DB::table('users')->insertGetId([
                'name' => 'Superadmin Bali Santih',
                'email' => 'superadmin@balisantih.test',
                'phone' => '6281230000001',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
                'status' => 'aktif',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('users')->insert([
                'name' => 'Admin BALIKOS',
                'email' => 'admin@balikos.test',
                'phone' => '6281230000002',
                'password' => Hash::make('password'),
                'role' => 'admin_balikos',
                'status' => 'aktif',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $ownerId = DB::table('users')->insertGetId([
                'name' => 'Made Pemilik Kos',
                'email' => 'pemilik@balikos.test',
                'phone' => '6281230000003',
                'password' => Hash::make('password'),
                'role' => 'pemilik_kos',
                'status' => 'aktif',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $kosMelatiId = DB::table('kos')->insertGetId([
                'owner_id' => $ownerId,
                'nama_kos' => 'Kos Melati Renon',
                'alamat' => 'Jalan Tukad Yeh Aya, Renon, Denpasar',
                'kecamatan' => 'Denpasar Selatan',
                'desa' => 'Renon',
                'banjar' => 'Pande',
                'latitude' => -8.6741120,
                'longitude' => 115.2322710,
                'no_wa' => '6281230000003',
                'aturan_kos' => 'Jam tamu sampai pukul 22.00 dan wajib menjaga kebersihan area bersama.',
                'catatan' => 'Kos khusus pekerja dan mahasiswa.',
                'status' => 'aktif',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $kosCempakaId = DB::table('kos')->insertGetId([
                'owner_id' => $ownerId,
                'nama_kos' => 'Kos Cempaka Sidakarya',
                'alamat' => 'Jalan Sidakarya, Denpasar Selatan',
                'kecamatan' => 'Denpasar Selatan',
                'desa' => 'Sidakarya',
                'banjar' => 'Dukuh Mertajati',
                'latitude' => -8.7012100,
                'longitude' => 115.2329900,
                'no_wa' => '6281230000003',
                'aturan_kos' => 'Pembayaran paling lambat tanggal 10 setiap bulan.',
                'catatan' => 'Parkir motor tersedia.',
                'status' => 'aktif',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $kamarIds = [];
            foreach ([
                [$kosMelatiId, 'A1', 'Standard', 1200000, 'terisi', true, true, true, true, true, false, true],
                [$kosMelatiId, 'A2', 'Standard', 1150000, 'kosong', false, true, true, true, true, false, true],
                [$kosMelatiId, 'B1', 'Deluxe', 1600000, 'maintenance', true, true, true, true, true, true, true],
                [$kosCempakaId, 'C1', 'Standard', 1000000, 'terisi', false, true, true, true, false, false, true],
                [$kosCempakaId, 'C2', 'Deluxe', 1450000, 'kosong', true, true, true, true, true, true, true],
            ] as $room) {
                $kamarIds[] = DB::table('kamars')->insertGetId([
                    'kos_id' => $room[0],
                    'nomor_kamar' => $room[1],
                    'tipe_kamar' => $room[2],
                    'harga_bulanan' => $room[3],
                    'status' => $room[4],
                    'fasilitas_ac' => $room[5],
                    'fasilitas_km_dalam' => $room[6],
                    'fasilitas_wifi' => $room[7],
                    'fasilitas_kasur' => $room[8],
                    'fasilitas_lemari' => $room[9],
                    'fasilitas_meja' => $room[10],
                    'fasilitas_parkir' => $room[11],
                    'foto' => null,
                    'catatan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $penghuniAId = DB::table('penghunis')->insertGetId([
                'kos_id' => $kosMelatiId,
                'kamar_id' => $kamarIds[0],
                'nama_lengkap' => 'Komang Surya',
                'no_ktp' => '5171010101010001',
                'no_wa' => '6281330000101',
                'alamat_asal' => 'Gianyar',
                'pekerjaan' => 'Karyawan',
                'no_kendaraan' => 'DK 1234 AB',
                'kontak_darurat' => '6281330000199',
                'tanggal_masuk' => '2026-01-10',
                'tanggal_keluar' => null,
                'status' => 'aktif',
                'active_kamar_id' => $kamarIds[0],
                'portal_token' => Str::random(48),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $penghuniBId = DB::table('penghunis')->insertGetId([
                'kos_id' => $kosCempakaId,
                'kamar_id' => $kamarIds[3],
                'nama_lengkap' => 'Ni Luh Arini',
                'no_ktp' => '5171010202020002',
                'no_wa' => '6281330000202',
                'alamat_asal' => 'Tabanan',
                'pekerjaan' => 'Mahasiswa',
                'no_kendaraan' => 'DK 5678 CD',
                'kontak_darurat' => '6281330000299',
                'tanggal_masuk' => '2026-02-01',
                'tanggal_keluar' => null,
                'status' => 'aktif',
                'active_kamar_id' => $kamarIds[3],
                'portal_token' => Str::random(48),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ([
                [$kosMelatiId, $kamarIds[0], $penghuniAId, 6, 2026, 1200000, '2026-06-10', 'lunas', 'transfer bank', '2026-06-05'],
                [$kosMelatiId, $kamarIds[0], $penghuniAId, 7, 2026, 1200000, '2026-07-10', 'belum_lunas', null, null],
                [$kosCempakaId, $kamarIds[3], $penghuniBId, 6, 2026, 1000000, '2026-06-10', 'lunas', 'tunai', '2026-06-08'],
                [$kosCempakaId, $kamarIds[3], $penghuniBId, 7, 2026, 1000000, '2026-07-10', 'menunggu_verifikasi', 'qris', null],
            ] as $bill) {
                DB::table('tagihans')->insert([
                    'kos_id' => $bill[0],
                    'kamar_id' => $bill[1],
                    'penghuni_id' => $bill[2],
                    'bulan' => $bill[3],
                    'tahun' => $bill[4],
                    'nominal' => $bill[5],
                    'tanggal_jatuh_tempo' => $bill[6],
                    'tanggal_bayar' => $bill[9],
                    'status' => $bill[7],
                    'metode_pembayaran' => $bill[8],
                    'bukti_pembayaran' => null,
                    'tanggal_konfirmasi' => $bill[7] === 'menunggu_verifikasi' ? $now : null,
                    'tanggal_verifikasi' => $bill[7] === 'lunas' ? $now : null,
                    'diverifikasi_oleh' => $bill[7] === 'lunas' ? $superadminId : null,
                    'alasan_penolakan' => null,
                    'catatan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('payment_methods')->insert([
                [
                    'kos_id' => $kosMelatiId,
                    'jenis' => 'bank',
                    'nama_bank' => 'BCA',
                    'nomor_rekening' => '1234567890',
                    'atas_nama' => 'Made Pemilik Kos',
                    'qris_image' => null,
                    'instruksi_pembayaran' => 'Transfer sesuai nominal tagihan lalu simpan bukti pembayaran.',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'kos_id' => $kosMelatiId,
                    'jenis' => 'tunai',
                    'nama_bank' => null,
                    'nomor_rekening' => null,
                    'atas_nama' => null,
                    'qris_image' => null,
                    'instruksi_pembayaran' => 'Bayar tunai langsung ke pemilik kos.',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'kos_id' => $kosCempakaId,
                    'jenis' => 'qris',
                    'nama_bank' => null,
                    'nomor_rekening' => null,
                    'atas_nama' => 'Made Pemilik Kos',
                    'qris_image' => 'seed/qris-cempaka.png',
                    'instruksi_pembayaran' => 'Scan QRIS dan unggah bukti melalui portal penghuni.',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            DB::table('kategori_keuangan')->insert([
                ['kos_id' => $kosMelatiId, 'nama_kategori' => 'Sewa Kamar', 'jenis' => 'pemasukan', 'created_at' => $now, 'updated_at' => $now],
                ['kos_id' => $kosMelatiId, 'nama_kategori' => 'Perawatan', 'jenis' => 'pengeluaran', 'created_at' => $now, 'updated_at' => $now],
                ['kos_id' => $kosCempakaId, 'nama_kategori' => 'Sewa Kamar', 'jenis' => 'pemasukan', 'created_at' => $now, 'updated_at' => $now],
            ]);

            DB::table('pengumuman_kos')->insert([
                [
                    'kos_id' => $kosMelatiId,
                    'judul' => 'Kerja bakti area parkir',
                    'isi' => 'Penghuni dimohon memindahkan motor pada hari Minggu pagi untuk pembersihan area parkir.',
                    'status' => 'aktif',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'kos_id' => $kosCempakaId,
                    'judul' => 'Pembayaran bulan Juli',
                    'isi' => 'Pembayaran bulan Juli dibuka sampai tanggal 10 Juli 2026.',
                    'status' => 'aktif',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        });
    }
}
