<?php

namespace App\Http\Controllers;

use App\Services\Balikos\XenditInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class BalikosPortalController extends Controller
{
    public function __construct(private readonly XenditInvoiceService $xendit) {}

    public function show(string $token)
    {
        $data = $this->portalData($token);

        return view('balikos.portal.show', $data);
    }

    public function manifest(string $token)
    {
        $penghuni = DB::table('penghunis')->where('portal_token', $token)->first();
        abort_if(! $penghuni, 404);

        return response()->json([
            'name' => 'Portal Penghuni BALIKOS',
            'short_name' => 'BALIKOS',
            'description' => 'Portal penghuni untuk melihat tagihan, info kos, dan pembayaran.',
            'start_url' => route('balikos.portal.show', $token, false),
            'scope' => '/balikos/portal/',
            'display' => 'standalone',
            'background_color' => '#f6f8f7',
            'theme_color' => '#0f766e',
            'icons' => [[
                'src' => '/balikos-portal-icon.svg',
                'sizes' => 'any',
                'type' => 'image/svg+xml',
                'purpose' => 'any maskable',
            ]],
        ])->header('Content-Type', 'application/manifest+json');
    }

    public function status(string $token)
    {
        $penghuni = DB::table('penghunis')->where('portal_token', $token)->first();
        abort_if(! $penghuni, 404);

        return response()->json([
            'tagihan_count' => DB::table('tagihans')->where('penghuni_id', $penghuni->id)->count(),
            'tagihan_open_count' => DB::table('tagihans')
                ->where('penghuni_id', $penghuni->id)
                ->whereIn('status', ['belum_lunas', 'terlambat', 'ditolak'])
                ->count(),
            'announcement_count' => DB::table('pengumuman_kos')
                ->where('kos_id', $penghuni->kos_id)
                ->where('status', 'aktif')
                ->count(),
            'latest_tagihan_update' => DB::table('tagihans')->where('penghuni_id', $penghuni->id)->max('updated_at'),
            'latest_announcement_update' => DB::table('pengumuman_kos')
                ->where('kos_id', $penghuni->kos_id)
                ->where('status', 'aktif')
                ->max('updated_at'),
        ]);
    }

    public function uploadProof(Request $request, string $token, int $tagihan)
    {
        $data = $request->validate([
            'bukti_pembayaran' => ['required', 'image', 'max:4096'],
            'metode_pembayaran' => ['nullable', 'string', 'max:255'],
            'tanggal_bayar' => ['nullable', 'date'],
        ]);

        $penghuni = DB::table('penghunis')->where('portal_token', $token)->first();
        abort_if(! $penghuni, 404);

        $bill = DB::table('tagihans')->where('id', $tagihan)->where('penghuni_id', $penghuni->id)->first();
        abort_if(! $bill, 404);

        $path = $request->file('bukti_pembayaran')->store('balikos/bukti-pembayaran', 'public');

        DB::table('tagihans')->where('id', $tagihan)->update([
            'status' => 'menunggu_verifikasi',
            'bukti_pembayaran' => $path,
            'metode_pembayaran' => $data['metode_pembayaran'] ?? 'transfer',
            'tanggal_bayar' => $data['tanggal_bayar'] ?? now()->toDateString(),
            'tanggal_konfirmasi' => now(),
            'alasan_penolakan' => null,
            'updated_at' => now(),
        ]);

        $kos = DB::table('kos')->where('id', $penghuni->kos_id)->first();
        if ($kos) {
            $this->sendOwnerPush(
                (int) $kos->owner_id,
                'Bukti pembayaran baru',
                $penghuni->nama_lengkap.' mengirim bukti pembayaran. Silakan verifikasi di menu Tagihan.',
                ['type' => 'payment_proof', 'tagihan_id' => $tagihan]
            );
        }

        return redirect()
            ->route('balikos.portal.show', $token)
            ->with('success', 'Bukti pembayaran berhasil dikirim. Pemilik kos akan mengecek pembayaran ini.');
    }

    public function payQris(string $token, int $tagihan)
    {
        $penghuni = DB::table('penghunis')->where('portal_token', $token)->first();
        abort_if(! $penghuni, 404);

        $bill = DB::table('tagihans')
            ->where('id', $tagihan)
            ->where('penghuni_id', $penghuni->id)
            ->first();
        abort_if(! $bill, 404);
        abort_if($bill->status === 'lunas', 422, 'Tagihan ini sudah lunas.');

        $method = DB::table('payment_methods')
            ->where('kos_id', $penghuni->kos_id)
            ->where('is_active', true)
            ->where('jenis', 'qris')
            ->first();
        abort_if(! $method, 422, 'Metode QRIS belum aktif untuk kos ini.');

        try {
            $bill = $this->xendit->ensureInvoiceForBill($bill, $token);
        } catch (HttpExceptionInterface $exception) {
            Log::warning('Portal QRIS payment failed', [
                'tagihan_id' => $tagihan,
                'status' => $exception->getStatusCode(),
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('balikos.portal.show', $token)
                ->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('balikos.portal.show', $token)
                ->with('error', 'Pembayaran QRIS belum dapat dibuka. Silakan coba beberapa saat lagi.');
        }

        return redirect()->away($bill->gateway_invoice_url);
    }

    public function xenditWebhook(Request $request)
    {
        return response()->json($this->xendit->handleInvoiceWebhook($request));
    }

    private function portalData(string $token): array
    {
        $penghuni = DB::table('penghunis')->where('portal_token', $token)->first();
        abort_if(! $penghuni, 404);

        $kos = DB::table('kos')->where('id', $penghuni->kos_id)->first();
        $kamar = DB::table('kamars')->where('id', $penghuni->kamar_id)->first();
        $tagihan = DB::table('tagihans')
            ->where('penghuni_id', $penghuni->id)
            ->orderByRaw("CASE status WHEN 'terlambat' THEN 0 WHEN 'ditolak' THEN 1 WHEN 'belum_lunas' THEN 2 WHEN 'menunggu_verifikasi' THEN 3 ELSE 4 END")
            ->orderBy('tahun')
            ->orderBy('bulan')
            ->get();
        $paymentMethods = DB::table('payment_methods')
            ->where('kos_id', $penghuni->kos_id)
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN jenis = 'qris' THEN 0 ELSE 1 END")
            ->get();
        $isQris = $paymentMethods->first() && ($paymentMethods->first()->jenis === 'qris' || $paymentMethods->first()->verification_mode === 'automatic');
        if ($isQris) {
            $tagihan = $tagihan->map(function ($bill) use ($token) {
                $bill->nominal_terbayar = (int) ($bill->nominal_terbayar ?? 0);
                $bill->sisa_tagihan = max(0, (int) $bill->nominal - (int) $bill->nominal_terbayar);
                $hasPendingInvoice = ! empty($bill->gateway_invoice_url)
                    && in_array($bill->gateway_status, [null, 'PENDING'], true);
                if (! $hasPendingInvoice) {
                    $bill->biaya_platform = (int) ceil(((int) $bill->sisa_tagihan) * (float) config('services.xendit.qris_fee_rate', 0.009));
                    $bill->total_dibayar = (int) $bill->sisa_tagihan + (int) $bill->biaya_platform;
                }
                $bill->qris_payment_url = route('balikos.portal.pay-qris', [$token, $bill->id]);

                return $bill;
            });
        }
        $announcements = DB::table('pengumuman_kos')
            ->where('kos_id', $penghuni->kos_id)
            ->where('status', 'aktif')
            ->orderByDesc('id')
            ->get();

        return compact('penghuni', 'kos', 'kamar', 'tagihan', 'paymentMethods', 'announcements');
    }

    private function sendOwnerPush(int $ownerId, string $title, string $body, array $data = []): void
    {
        $tokens = DB::table('push_notification_tokens')
            ->where('user_id', $ownerId)
            ->where('provider', 'expo')
            ->pluck('token')
            ->filter(fn ($token) => str_starts_with($token, 'ExponentPushToken[') || str_starts_with($token, 'ExpoPushToken['))
            ->values();

        if ($tokens->isEmpty()) {
            return;
        }

        $messages = $tokens->map(fn ($token) => [
            'to' => $token,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ])->all();

        try {
            Http::timeout(5)->post('https://exp.host/--/api/v2/push/send', $messages);
        } catch (Throwable) {
            //
        }
    }
}
