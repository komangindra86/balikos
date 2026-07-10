<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            'foto_ktp' => UploadedFile::fake()->image('ktp.jpg'),
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
            'bukti_pembayaran' => UploadedFile::fake()->image('bukti.jpg'),
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

        $this->withToken($token)->postJson('/api/balikos/pengumuman', [
            'kos_id' => $kosId,
            'judul' => 'Info Test',
            'isi' => 'Air mati jam 10 malam.',
            'status' => 'aktif',
        ])->assertCreated()->assertJsonPath('data.judul', 'Info Test');
    }
}
