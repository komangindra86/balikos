<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BalikosApiFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_room_data_is_isolated_between_owner_accounts(): void
    {
        $suffix = Str::lower(Str::random(8));
        $registerOwner = function (string $name, string $email): array {
            return $this->postJson('/api/balikos/register', [
                'name' => $name,
                'email' => $email,
                'phone' => '081234567890',
                'password' => 'password',
                'password_confirmation' => 'password',
                'device_name' => 'isolation-test',
            ])->assertCreated()->json();
        };

        $ownerOne = $registerOwner('Owner One', "owner-one-{$suffix}@balikos.test");
        $ownerTwo = $registerOwner('Owner Two', "owner-two-{$suffix}@balikos.test");

        $kosOne = $this->withToken($ownerOne['token'])->postJson('/api/balikos/kos', [
            'nama_kos' => 'Kos Owner One',
            'alamat' => 'Alamat owner one',
            'kecamatan' => 'Denpasar Selatan',
            'no_wa' => '081111111111',
            'status' => 'aktif',
        ])->assertCreated()->json('data');
        $kosTwo = $this->withToken($ownerTwo['token'])->postJson('/api/balikos/kos', [
            'nama_kos' => 'Kos Owner Two',
            'alamat' => 'Alamat owner two',
            'kecamatan' => 'Denpasar Barat',
            'no_wa' => '082222222222',
            'status' => 'aktif',
        ])->assertCreated()->json('data');

        $roomOne = $this->withToken($ownerOne['token'])->postJson('/api/balikos/kamar', [
            'kos_id' => $kosOne['id'],
            'nomor_kamar' => 'OWNER-ONE',
            'tipe_kamar' => 'Standard',
            'harga_bulanan' => 1000000,
            'status' => 'kosong',
        ])->assertCreated()->json('data');
        $roomTwo = $this->withToken($ownerTwo['token'])->postJson('/api/balikos/kamar', [
            'kos_id' => $kosTwo['id'],
            'nomor_kamar' => 'OWNER-TWO',
            'tipe_kamar' => 'Standard',
            'harga_bulanan' => 1100000,
            'status' => 'kosong',
        ])->assertCreated()->json('data');

        $ownerOneRooms = $this->withToken($ownerOne['token'])
            ->getJson('/api/balikos/kamar')
            ->assertOk()
            ->json('data');

        $this->assertContains($roomOne['id'], collect($ownerOneRooms)->pluck('id')->all());
        $this->assertNotContains($roomTwo['id'], collect($ownerOneRooms)->pluck('id')->all());
        $this->withToken($ownerOne['token'])
            ->getJson('/api/balikos/kamar?kos_id='.$kosTwo['id'])
            ->assertForbidden();
    }

    public function test_owner_can_fill_empty_room_and_generate_room_bill(): void
    {
        $login = $this->postJson('/api/balikos/login', [
            'email' => 'pemilik@balikos.test',
            'password' => 'password',
            'device_name' => 'feature-test',
        ])->assertOk()->json();

        $token = $login['token'];
        $kosId = DB::table('kos')->where('owner_id', $login['user']['id'])->value('id');
        $this->assertNotEmpty($kosId);

        $room = $this->withToken($token)->postJson('/api/balikos/kamar', [
            'kos_id' => $kosId,
            'nomor_kamar' => 'TEST-'.random_int(10000, 99999),
            'tipe_kamar' => 'Test',
            'harga_bulanan' => 123000,
            'status' => 'kosong',
            'fasilitas_wifi' => true,
            'fasilitas_km_dalam' => true,
            'fasilitas_dapur_dalam' => true,
        ])->assertCreated()->json('data');

        $penghuni = $this->withToken($token)->postJson('/api/balikos/penghuni', [
            'kos_id' => $kosId,
            'kamar_id' => $room['id'],
            'nama_lengkap' => 'Penghuni Test',
            'no_wa' => '08123456789',
            'tanggal_masuk' => now()->toDateString(),
            'jatuh_tempo_hari' => 5,
            'status' => 'aktif',
        ])->assertCreated()->json('data');

        $this->assertSame('terisi', DB::table('kamars')->where('id', $room['id'])->value('status'));

        Storage::fake('public');
        $this->withToken($token)->post('/api/balikos/penghuni', [
            'kos_id' => $kosId,
            'kamar_id' => $room['id'],
            'nama_lengkap' => 'Penghuni KTP Test',
            'tanggal_masuk' => now()->toDateString(),
            'status' => 'keluar',
            'foto_ktp' => $this->fakePngUpload('ktp.png'),
        ])->assertCreated()->assertJsonPath('data.nama_lengkap', 'Penghuni KTP Test');

        $ktpPath = DB::table('penghunis')->where('nama_lengkap', 'Penghuni KTP Test')->value('foto_ktp');
        $this->assertNotEmpty($ktpPath);
        Storage::disk('public')->assertExists($ktpPath);

        $this->withToken($token)->getJson('/api/balikos/kamar/'.$room['id'])
            ->assertOk()
            ->assertJsonPath('data.penghuni_aktif.id', $penghuni['id'])
            ->assertJsonPath('data.fasilitas_dapur_dalam', 1);

        $this->withToken($token)->postJson('/api/balikos/tagihan/generate', [
            'kos_id' => $kosId,
            'kamar_id' => $room['id'],
            'bulan' => (int) now()->format('m'),
            'tahun' => (int) now()->format('Y'),
        ])->assertOk()->assertJsonPath('total', 1);

        $currentPeriod = now()->startOfMonth();
        $this->assertSame(
            $currentPeriod->copy()->setDay(5)->toDateString(),
            DB::table('tagihans')->where('penghuni_id', $penghuni['id'])->where('bulan', (int) $currentPeriod->month)->where('tahun', (int) $currentPeriod->year)->value('tanggal_jatuh_tempo')
        );

        $tagihanId = DB::table('tagihans')->where('penghuni_id', $penghuni['id'])->where('bulan', (int) $currentPeriod->month)->where('tahun', (int) $currentPeriod->year)->value('id');
        $this->post('/api/balikos/portal/'.$penghuni['portal_token'].'/tagihan/'.$tagihanId.'/bukti', [
            'bukti_pembayaran' => $this->fakePngUpload('bukti.png'),
            'metode_pembayaran' => 'transfer',
            'tanggal_bayar' => now()->toDateString(),
        ])->assertOk()->assertJsonPath('data.status', 'menunggu_verifikasi');

        $proofPath = DB::table('tagihans')->where('id', $tagihanId)->value('bukti_pembayaran');
        $this->assertNotEmpty($proofPath);
        Storage::disk('public')->assertExists($proofPath);

        $this->withToken($token)->putJson('/api/balikos/tagihan/'.$tagihanId.'/verifikasi')
            ->assertOk()
            ->assertJsonPath('data.status', 'lunas');

        $this->withToken($token)->postJson('/api/balikos/penghuni', [
            'kos_id' => $kosId,
            'kamar_id' => $room['id'],
            'nama_lengkap' => 'Duplikat Penghuni',
            'tanggal_masuk' => now()->toDateString(),
            'status' => 'aktif',
        ])->assertStatus(422)->assertJsonPath('message', 'Kamar ini sudah memiliki penghuni aktif.');

        $this->withToken($token)->postJson('/api/balikos/tagihan/bayar-multi', [
            'kos_id' => $kosId,
            'kamar_id' => $room['id'],
            'bulan' => (int) now()->format('m'),
            'tahun' => (int) now()->format('Y'),
            'jumlah_bulan' => 2,
            'tanggal_bayar' => now()->toDateString(),
            'metode_pembayaran' => 'tunai',
        ])->assertOk()->assertJsonPath('total', 2);

        $nextPeriod = $currentPeriod->copy()->addMonth();
        $this->assertSame(
            $nextPeriod->copy()->setDay(5)->toDateString(),
            DB::table('tagihans')->where('penghuni_id', $penghuni['id'])->where('bulan', (int) $nextPeriod->month)->where('tahun', (int) $nextPeriod->year)->value('tanggal_jatuh_tempo')
        );

        $this->withToken($token)->putJson('/api/balikos/penghuni/'.$penghuni['id'], [
            'status' => 'keluar',
            'tanggal_keluar' => now()->toDateString(),
        ])->assertOk();

        $this->assertSame('kosong', DB::table('kamars')->where('id', $room['id'])->value('status'));

        $this->withToken($token)->putJson('/api/balikos/kamar/'.$room['id'], [
            'status' => 'maintenance',
        ])->assertOk()->assertJsonPath('data.status', 'maintenance');

        $this->withToken($token)->putJson('/api/balikos/kamar/'.$room['id'], [
            'status' => 'kosong',
        ])->assertOk()->assertJsonPath('data.status', 'kosong');
    }

    public function test_owner_can_upload_multiple_room_photos(): void
    {
        Storage::fake('public');

        $login = $this->postJson('/api/balikos/login', [
            'email' => 'pemilik@balikos.test',
            'password' => 'password',
            'device_name' => 'feature-test',
        ])->assertOk()->json();

        $token = $login['token'];
        $kosId = DB::table('kos')->where('owner_id', $login['user']['id'])->value('id');

        $room = $this->withToken($token)->post('/api/balikos/kamar', [
            'kos_id' => $kosId,
            'nomor_kamar' => 'FOTO-'.random_int(10000, 99999),
            'tipe_kamar' => 'Foto Test',
            'harga_bulanan' => 1500000,
            'status' => 'kosong',
            'foto' => UploadedFile::fake()->image('kamar-utama.png', 432, 888),
            'fotos' => [
                UploadedFile::fake()->image('kamar-1.png', 432, 888),
                UploadedFile::fake()->image('kamar-2.png', 432, 888),
            ],
        ])->assertCreated()
            ->assertJsonCount(3, 'data.fotos')
            ->json('data');

        $this->assertSame(3, DB::table('kamar_fotos')->where('kamar_id', $room['id'])->count());
        $this->assertSame($room['fotos'][0]['path'], DB::table('kamars')->where('id', $room['id'])->value('foto'));
        $this->assertStringEndsWith('.jpg', $room['fotos'][0]['path']);
        Storage::disk('public')->assertExists($room['fotos'][0]['path']);
        Storage::disk('public')->assertExists($room['fotos'][1]['path']);
        Storage::disk('public')->assertExists($room['fotos'][2]['path']);

        $this->withToken($token)->post('/api/balikos/kamar/'.$room['id'], [
            '_method' => 'PUT',
            'hapus_foto_ids' => [$room['fotos'][0]['id']],
            'foto' => UploadedFile::fake()->image('kamar-baru.png', 432, 888),
        ])->assertOk()->assertJsonCount(3, 'data.fotos');

        $this->assertSame(3, DB::table('kamar_fotos')->where('kamar_id', $room['id'])->count());
    }

    public function test_owner_can_use_payment_finance_and_announcement_menus(): void
    {
        $login = $this->postJson('/api/balikos/login', [
            'email' => 'pemilik@balikos.test',
            'password' => 'password',
            'device_name' => 'feature-test',
        ])->assertOk()->json();

        $token = $login['token'];
        $kosId = DB::table('kos')->where('owner_id', $login['user']['id'])->value('id');

        $this->withToken($token)->postJson('/api/balikos/payment-methods', [
            'kos_id' => $kosId,
            'jenis' => 'bank',
            'nama_bank' => 'BCA Test',
            'nomor_rekening' => '1234567890',
            'atas_nama' => 'Pemilik Test',
            'instruksi_pembayaran' => 'Transfer lalu konfirmasi.',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.nama_bank', 'BCA Test');

        $finance = $this->withToken($token)->postJson('/api/balikos/keuangan', [
            'kos_id' => $kosId,
            'jenis' => 'pengeluaran',
            'tanggal' => now()->toDateString(),
            'nominal' => 50000,
            'keterangan' => 'Beli alat kebersihan',
        ])->assertCreated()->assertJsonPath('data.nominal', 50000);

        $financeId = $finance->json('data.id');
        $this->withToken($token)->putJson('/api/balikos/keuangan/'.$financeId, [
            'kos_id' => $kosId,
            'jenis' => 'pengeluaran',
            'tanggal' => now()->toDateString(),
            'nominal' => 60000,
            'keterangan' => 'Beli alat kebersihan dan sabun',
        ])->assertOk()
            ->assertJsonPath('data.nominal', 60000)
            ->assertJsonPath('data.keterangan', 'Beli alat kebersihan dan sabun');

        $summary = $this->withToken($token)->getJson('/api/balikos/keuangan?kos_id='.$kosId.'&bulan='.(int) now()->format('m').'&tahun='.(int) now()->format('Y'))
            ->assertOk()
            ->assertJsonPath('summary.pengeluaran', 60000)
            ->assertJsonPath('summary.status', 'rugi')
            ->json('summary');
        $this->assertSame(
            (int) $summary['total_pemasukan'] - (int) $summary['pengeluaran'] - (int) $summary['kerugian_tunggakan'],
            (int) $summary['laba_rugi']
        );

        $pdf = $this->withToken($token)->get('/api/balikos/keuangan/laporan-pdf?kos_id='.$kosId.'&bulan='.(int) now()->format('m').'&tahun='.(int) now()->format('Y'));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $announcement = $this->withToken($token)->postJson('/api/balikos/pengumuman', [
            'kos_id' => $kosId,
            'judul' => 'Info Test',
            'isi' => 'Air mati jam 10 malam.',
            'status' => 'aktif',
        ])->assertCreated()->assertJsonPath('data.judul', 'Info Test');

        $announcementId = $announcement->json('data.id');
        $this->withToken($token)->putJson('/api/balikos/pengumuman/'.$announcementId, [
            'kos_id' => $kosId,
            'judul' => 'Info Test Diperbarui',
            'isi' => 'Air kembali menyala jam 11 malam.',
            'status' => 'nonaktif',
        ])->assertOk()
            ->assertJsonPath('data.judul', 'Info Test Diperbarui')
            ->assertJsonPath('data.status', 'nonaktif');

        $this->withToken($token)->deleteJson('/api/balikos/pengumuman/'.$announcementId)->assertOk();
        $this->assertFalse(DB::table('pengumuman_kos')->where('id', $announcementId)->exists());

        $this->withToken($token)->deleteJson('/api/balikos/keuangan/'.$financeId)->assertOk();
        $this->assertFalse(DB::table('transaksi_keuangan')->where('id', $financeId)->exists());
    }

    public function test_new_occupant_initial_payment_creates_first_bill(): void
    {
        $login = $this->postJson('/api/balikos/login', [
            'email' => 'pemilik@balikos.test',
            'password' => 'password',
            'device_name' => 'feature-test',
        ])->assertOk()->json();

        $token = $login['token'];
        $kosId = DB::table('kos')->where('owner_id', $login['user']['id'])->value('id');

        $room = $this->withToken($token)->postJson('/api/balikos/kamar', [
            'kos_id' => $kosId,
            'nomor_kamar' => 'DP-'.random_int(10000, 99999),
            'tipe_kamar' => 'Test',
            'harga_bulanan' => 1000000,
            'status' => 'kosong',
        ])->assertCreated()->json('data');

        $dpOccupant = $this->withToken($token)->postJson('/api/balikos/penghuni', [
            'kos_id' => $kosId,
            'kamar_id' => $room['id'],
            'nama_lengkap' => 'Penghuni DP Test',
            'tanggal_masuk' => '2026-07-16',
            'status' => 'aktif',
            'pembayaran_awal' => 'dp',
            'nominal_pembayaran_awal' => 300000,
        ])->assertCreated()->json('data');

        $dpBill = DB::table('tagihans')->where('penghuni_id', $dpOccupant['id'])->first();
        $this->assertSame(1000000, (int) $dpBill->nominal);
        $this->assertSame(300000, (int) $dpBill->nominal_terbayar);
        $this->assertSame('belum_lunas', $dpBill->status);

        $this->withToken($token)->getJson('/api/balikos/penghuni?kos_id='.$kosId)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $dpOccupant['id'],
                'tagihan_aktif_nominal' => 700000,
            ]);

        $lunasRoom = $this->withToken($token)->postJson('/api/balikos/kamar', [
            'kos_id' => $kosId,
            'nomor_kamar' => 'LUNAS-'.random_int(10000, 99999),
            'tipe_kamar' => 'Test',
            'harga_bulanan' => 900000,
            'status' => 'kosong',
        ])->assertCreated()->json('data');

        $lunasOccupant = $this->withToken($token)->postJson('/api/balikos/penghuni', [
            'kos_id' => $kosId,
            'kamar_id' => $lunasRoom['id'],
            'nama_lengkap' => 'Penghuni Lunas Test',
            'tanggal_masuk' => '2026-07-16',
            'status' => 'aktif',
            'pembayaran_awal' => 'lunas',
        ])->assertCreated()->json('data');

        $lunasBill = DB::table('tagihans')->where('penghuni_id', $lunasOccupant['id'])->first();
        $this->assertSame(900000, (int) $lunasBill->nominal_terbayar);
        $this->assertSame('lunas', $lunasBill->status);
    }

    public function test_dp_is_reported_corrected_and_remaining_balance_becomes_checkout_loss(): void
    {
        $suffix = Str::lower(Str::random(8));
        $owner = $this->postJson('/api/balikos/register', [
            'name' => 'Owner DP',
            'email' => "owner-dp-{$suffix}@balikos.test",
            'phone' => '081234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'dp-test',
        ])->assertCreated()->json();

        $kos = $this->withToken($owner['token'])->postJson('/api/balikos/kos', [
            'nama_kos' => 'Kos DP',
            'alamat' => 'Alamat tes DP',
            'kecamatan' => 'Denpasar',
            'no_wa' => '081234567890',
            'status' => 'aktif',
        ])->assertCreated()->json('data');
        $room = $this->withToken($owner['token'])->postJson('/api/balikos/kamar', [
            'kos_id' => $kos['id'],
            'nomor_kamar' => 'DP-1',
            'tipe_kamar' => 'Standard',
            'harga_bulanan' => 1000000,
            'status' => 'kosong',
        ])->assertCreated()->json('data');
        $occupant = $this->withToken($owner['token'])->postJson('/api/balikos/penghuni', [
            'kos_id' => $kos['id'],
            'kamar_id' => $room['id'],
            'nama_lengkap' => 'Penghuni DP',
            'tanggal_masuk' => '2026-07-20',
            'status' => 'aktif',
            'pembayaran_awal' => 'dp',
            'nominal_pembayaran_awal' => 500000,
        ])->assertCreated()->json('data');
        $bill = DB::table('tagihans')->where('penghuni_id', $occupant['id'])->first();

        $this->assertSame(500000, (int) DB::table('tagihan_pembayarans')->where('tagihan_id', $bill->id)->sum('nominal'));
        $this->withToken($owner['token'])
            ->getJson('/api/balikos/keuangan?kos_id='.$kos['id'].'&bulan=7&tahun=2026')
            ->assertOk()
            ->assertJsonPath('summary.pendapatan_sewa', 500000)
            ->assertJsonPath('summary.laba_rugi', 500000);

        DB::table('tagihan_pembayarans')
            ->where('tagihan_id', $bill->id)
            ->where('sumber', 'pembayaran_awal')
            ->update([
                'sumber' => 'riwayat_lama',
                'catatan' => 'Riwayat pembayaran sebelum pencatatan pembayaran per transaksi diaktifkan.',
            ]);
        $this->withToken($owner['token'])
            ->getJson('/api/balikos/tagihan/'.$bill->id)
            ->assertOk()
            ->assertJsonPath('data.bisa_koreksi_dp', true);

        $this->withToken($owner['token'])->putJson('/api/balikos/tagihan/'.$bill->id.'/pembayaran-awal', [
            'nominal' => 600000,
            'tanggal_bayar' => '2026-07-20',
        ])->assertOk()->assertJsonPath('data.nominal_terbayar', 600000);
        $this->assertSame(600000, (int) DB::table('tagihan_pembayarans')->where('tagihan_id', $bill->id)->sum('nominal'));
        $this->assertSame('pembayaran_awal', DB::table('tagihan_pembayarans')->where('tagihan_id', $bill->id)->value('sumber'));

        $this->withToken($owner['token'])->putJson('/api/balikos/penghuni/'.$occupant['id'], [
            'status' => 'keluar',
            'tanggal_keluar' => '2026-07-21',
        ])->assertOk();

        $this->assertSame(400000, (int) DB::table('tagihans')->where('id', $bill->id)->value('kerugian_tunggakan'));
        $this->withToken($owner['token'])
            ->getJson('/api/balikos/keuangan?kos_id='.$kos['id'].'&bulan=7&tahun=2026')
            ->assertOk()
            ->assertJsonPath('summary.pendapatan_sewa', 600000)
            ->assertJsonPath('summary.kerugian_tunggakan', 400000)
            ->assertJsonPath('summary.laba_rugi', 200000);
    }

    public function test_qris_verification_is_idempotent_for_wallet_credit(): void
    {
        $login = $this->postJson('/api/balikos/login', [
            'email' => 'pemilik@balikos.test',
            'password' => 'password',
            'device_name' => 'feature-test',
        ])->assertOk()->json();

        $token = $login['token'];
        $kosId = DB::table('kos')->where('owner_id', $login['user']['id'])->value('id');

        $this->withToken($token)->postJson('/api/balikos/payment-methods', [
            'kos_id' => $kosId,
            'jenis' => 'qris',
            'is_active' => true,
        ])->assertCreated();

        $room = $this->withToken($token)->postJson('/api/balikos/kamar', [
            'kos_id' => $kosId,
            'nomor_kamar' => 'QRIS-'.random_int(10000, 99999),
            'tipe_kamar' => 'Test',
            'harga_bulanan' => 1000000,
            'status' => 'kosong',
        ])->assertCreated()->json('data');

        $penghuni = $this->withToken($token)->postJson('/api/balikos/penghuni', [
            'kos_id' => $kosId,
            'kamar_id' => $room['id'],
            'nama_lengkap' => 'Penghuni QRIS Test',
            'tanggal_masuk' => now()->toDateString(),
            'status' => 'aktif',
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson('/api/balikos/tagihan/generate', [
            'kos_id' => $kosId,
            'kamar_id' => $room['id'],
            'bulan' => (int) now()->format('m'),
            'tahun' => (int) now()->format('Y'),
        ])->assertOk();

        $tagihanId = DB::table('tagihans')->where('penghuni_id', $penghuni['id'])->value('id');
        $walletId = DB::table('kos_wallets')->where('kos_id', $kosId)->value('id');
        DB::table('kos_wallets')->where('id', $walletId)->update(['saldo_tersedia' => 0]);

        $this->withToken($token)->putJson('/api/balikos/tagihan/'.$tagihanId.'/verifikasi')
            ->assertOk()
            ->assertJsonPath('data.status', 'lunas');

        $this->assertSame(1000000, (int) DB::table('kos_wallets')->where('id', $walletId)->value('saldo_tersedia'));

        $this->withToken($token)->putJson('/api/balikos/tagihan/'.$tagihanId.'/verifikasi')
            ->assertOk()
            ->assertJsonPath('data.status', 'lunas');

        $this->assertSame(1000000, (int) DB::table('kos_wallets')->where('id', $walletId)->value('saldo_tersedia'));
    }

    public function test_xendit_invoice_and_webhook_are_idempotent(): void
    {
        config([
            'services.xendit.secret_key' => 'xnd_test_secret',
            'services.xendit.webhook_token' => 'callback-token-test',
        ]);

        $login = $this->postJson('/api/balikos/login', [
            'email' => 'pemilik@balikos.test',
            'password' => 'password',
            'device_name' => 'feature-test',
        ])->assertOk()->json();

        $token = $login['token'];
        $kosId = DB::table('kos')->where('owner_id', $login['user']['id'])->value('id');

        $this->withToken($token)->postJson('/api/balikos/payment-methods', [
            'kos_id' => $kosId,
            'jenis' => 'qris',
            'is_active' => true,
        ])->assertCreated();

        $room = $this->withToken($token)->postJson('/api/balikos/kamar', [
            'kos_id' => $kosId,
            'nomor_kamar' => 'XENDIT-'.random_int(10000, 99999),
            'tipe_kamar' => 'Test',
            'harga_bulanan' => 1000000,
            'status' => 'kosong',
        ])->assertCreated()->json('data');

        $penghuni = $this->withToken($token)->postJson('/api/balikos/penghuni', [
            'kos_id' => $kosId,
            'kamar_id' => $room['id'],
            'nama_lengkap' => 'Penghuni Xendit Test',
            'tanggal_masuk' => now()->toDateString(),
            'status' => 'aktif',
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson('/api/balikos/tagihan/generate', [
            'kos_id' => $kosId,
            'kamar_id' => $room['id'],
            'bulan' => (int) now()->format('m'),
            'tahun' => (int) now()->format('Y'),
        ])->assertOk();

        $tagihan = DB::table('tagihans')->where('penghuni_id', $penghuni['id'])->first();
        $this->assertSame(9000, (int) $tagihan->biaya_platform);
        $this->assertSame(1009000, (int) $tagihan->total_dibayar);
        $environment = substr(hash('sha256', config('app.url').'|'.config('app.env')), 0, 10);
        $expectedReference = 'balikos-'.$environment.'-tagihan-'.$tagihan->id;

        $invoicePostAttempts = 0;
        Http::fake(function ($request) use (&$invoicePostAttempts, $expectedReference, $tagihan) {
            if ($request->method() === 'GET') {
                return Http::response([[
                    'id' => 'inv-test-'.$tagihan->id,
                    'external_id' => $expectedReference,
                    'amount' => 1009000,
                    'status' => 'PENDING',
                    'invoice_url' => 'https://checkout.xendit.co/web/inv-test-'.$tagihan->id,
                ]], 200);
            }

            $invoicePostAttempts++;
            if ($invoicePostAttempts === 1) {
                return Http::response([
                    'error_code' => 'REQUEST_FORBIDDEN_ERROR',
                    'message' => 'QRIS is not available.',
                ], 403);
            }
            if ($invoicePostAttempts === 2) {
                return Http::response([
                    'id' => 'inv-test-'.$tagihan->id,
                    'external_id' => $expectedReference,
                    'amount' => 1009000,
                    'status' => 'PENDING',
                    'invoice_url' => 'https://checkout.xendit.co/web/inv-test-'.$tagihan->id,
                ], 200);
            }

            return Http::response([
                'error_code' => 'DUPLICATE_ERROR',
                'message' => 'Invoice with this external ID already exists.',
            ], 409);
        });
        $this->get('/balikos/portal/'.$penghuni['portal_token'].'/tagihan/'.$tagihan->id.'/qris')
            ->assertRedirect(route('balikos.portal.show', $penghuni['portal_token']))
            ->assertSessionHas('error', 'Gagal membuat link QRIS. Silakan coba lagi beberapa saat.');

        $this->getJson('/api/balikos/portal/'.$penghuni['portal_token'].'/tagihan/'.$tagihan->id.'/qris')
            ->assertOk()
            ->assertJsonPath('data.invoice_url', 'https://checkout.xendit.co/web/inv-test-'.$tagihan->id);

        DB::table('tagihans')->where('id', $tagihan->id)->update([
            'gateway_invoice_id' => null,
            'gateway_invoice_url' => null,
            'gateway_status' => null,
            'gateway_payload' => null,
        ]);
        $this->getJson('/api/balikos/portal/'.$penghuni['portal_token'].'/tagihan/'.$tagihan->id.'/qris')
            ->assertOk()
            ->assertJsonPath('data.invoice_url', 'https://checkout.xendit.co/web/inv-test-'.$tagihan->id);
        $this->assertSame(
            'https://checkout.xendit.co/web/inv-test-'.$tagihan->id,
            DB::table('tagihans')->where('id', $tagihan->id)->value('gateway_invoice_url')
        );

        $walletId = DB::table('kos_wallets')->where('kos_id', $kosId)->value('id');
        DB::table('kos_wallets')->where('id', $walletId)->update(['saldo_tersedia' => 0]);

        $payload = [
            'id' => 'inv-test-'.$tagihan->id,
            'external_id' => $expectedReference,
            'status' => 'PAID',
            'paid_amount' => 1009000,
            'amount' => 1009000,
            'payment_id' => 'qrpy-test-'.$tagihan->id,
            'payment_method' => 'QR_CODE',
            'payment_channel' => 'QRIS',
            'paid_at' => now()->toIso8601String(),
        ];

        $this->withHeader('x-callback-token', 'callback-token-test')
            ->postJson('/api/balikos/xendit/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('tagihan_id', $tagihan->id);

        $this->assertSame('lunas', DB::table('tagihans')->where('id', $tagihan->id)->value('status'));
        $this->assertSame(1000000, (int) DB::table('kos_wallets')->where('id', $walletId)->value('saldo_tersedia'));

        $this->withHeader('x-callback-token', 'callback-token-test')
            ->postJson('/api/balikos/xendit/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(1000000, (int) DB::table('kos_wallets')->where('id', $walletId)->value('saldo_tersedia'));
    }

    private function fakePngUpload(string $name): UploadedFile
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.Str::random(16).'-'.$name;
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
