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
            ->assertJsonPath('data.penghuni_aktif.id', $penghuni['id']);

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

        $this->withToken($token)->postJson('/api/balikos/keuangan', [
            'kos_id' => $kosId,
            'jenis' => 'pengeluaran',
            'tanggal' => now()->toDateString(),
            'nominal' => 50000,
            'keterangan' => 'Beli alat kebersihan',
        ])->assertCreated()->assertJsonPath('data.nominal', 50000);

        $this->withToken($token)->getJson('/api/balikos/keuangan?kos_id='.$kosId.'&bulan='.(int) now()->format('m').'&tahun='.(int) now()->format('Y'))
            ->assertOk()
            ->assertJsonPath('summary.pengeluaran', 50000)
            ->assertJsonPath('summary.laba_rugi', -50000)
            ->assertJsonPath('summary.status', 'rugi');

        $pdf = $this->withToken($token)->get('/api/balikos/keuangan/laporan-pdf?kos_id='.$kosId.'&bulan='.(int) now()->format('m').'&tahun='.(int) now()->format('Y'));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->withToken($token)->postJson('/api/balikos/pengumuman', [
            'kos_id' => $kosId,
            'judul' => 'Info Test',
            'isi' => 'Air mati jam 10 malam.',
            'status' => 'aktif',
        ])->assertCreated()->assertJsonPath('data.judul', 'Info Test');
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
        $expectedReference = 'balikos-tagihan-'.$tagihan->id;

        Http::fake([
            'https://api.xendit.co/v2/invoices' => Http::response([
                'id' => 'inv-test-'.$tagihan->id,
                'external_id' => $expectedReference,
                'status' => 'PENDING',
                'invoice_url' => 'https://checkout.xendit.co/web/inv-test-'.$tagihan->id,
            ], 200),
        ]);

        $this->getJson('/api/balikos/portal/'.$penghuni['portal_token'].'/tagihan/'.$tagihan->id.'/qris')
            ->assertOk()
            ->assertJsonPath('data.invoice_url', 'https://checkout.xendit.co/web/inv-test-'.$tagihan->id);

        $walletId = DB::table('kos_wallets')->where('kos_id', $kosId)->value('id');
        DB::table('kos_wallets')->where('id', $walletId)->update(['saldo_tersedia' => 0]);

        $payload = [
            'id' => 'inv-test-'.$tagihan->id,
            'external_id' => $expectedReference,
            'status' => 'PAID',
            'paid_amount' => 1010000,
            'amount' => 1010000,
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
