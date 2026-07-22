<?php

namespace App\Services\Balikos;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class XenditInvoiceService
{
    public function ensureInvoiceForBill(object $bill, ?string $portalToken = null): object
    {
        if (! empty($bill->gateway_invoice_url) && in_array($bill->gateway_status, [null, 'PENDING'], true)) {
            return $bill;
        }

        $secretKey = (string) config('services.xendit.secret_key');
        abort_if($secretKey === '', 422, 'Konfigurasi QRIS otomatis belum lengkap. Hubungi admin BALIKOS.');

        $bill = $this->prepareBillForXendit($bill);
        $penghuni = DB::table('penghunis')->where('id', $bill->penghuni_id)->first();
        $kos = DB::table('kos')->where('id', $bill->kos_id)->first();
        $kamar = DB::table('kamars')->where('id', $bill->kamar_id)->first();
        abort_if(! $penghuni || ! $kos || ! $kamar, 404, 'Data tagihan tidak lengkap.');

        $reference = $bill->gateway_reference ?: $this->invoiceReference($bill);
        $redirectUrl = $portalToken
            ? route('balikos.portal.show', $portalToken)
            : url('/balikos/portal/'.$penghuni->portal_token);

        abort_if((int) $bill->total_dibayar < 1500, 422, 'Total pembayaran QRIS minimal Rp 1.500.');

        $customer = array_filter([
            'given_names' => $penghuni->nama_lengkap,
            'mobile_number' => $this->normalizePhone($penghuni->no_wa ?? null),
        ], fn ($value) => $value !== null && $value !== '');

        $items = [[
            'name' => 'Sewa kamar '.$kamar->nomor_kamar,
            'quantity' => 1,
            'price' => max(0, (int) $bill->nominal - (int) ($bill->nominal_terbayar ?? 0)),
            'category' => 'Rent',
        ]];
        if ((int) $bill->biaya_platform > 0) {
            $items[] = [
                'name' => 'Biaya layanan QRIS',
                'quantity' => 1,
                'price' => (int) $bill->biaya_platform,
                'category' => 'Service Fee',
            ];
        }

        $payload = [
            'external_id' => $reference,
            'amount' => (int) $bill->total_dibayar,
            'description' => 'Tagihan kos '.$kos->nama_kos.' kamar '.$kamar->nomor_kamar.' periode '.str_pad((string) $bill->bulan, 2, '0', STR_PAD_LEFT).'/'.$bill->tahun,
            'invoice_duration' => 86400 * 14,
            'currency' => 'IDR',
            'payment_methods' => ['QRIS'],
            'success_redirect_url' => $redirectUrl,
            'failure_redirect_url' => $redirectUrl,
            'should_send_email' => false,
            'customer' => $customer,
            'items' => $items,
            'metadata' => [
                'app' => 'BALIKOS',
                'tagihan_id' => (int) $bill->id,
                'kos_id' => (int) $bill->kos_id,
                'penghuni_id' => (int) $bill->penghuni_id,
            ],
        ];

        try {
            $response = $this->xenditRequest($secretKey)
                ->asJson()
                ->post('https://api.xendit.co/v2/invoices', $payload);
        } catch (ConnectionException $exception) {
            Log::error('Xendit invoice connection failed', [
                'tagihan_id' => $bill->id,
                'message' => $exception->getMessage(),
            ]);
            abort(503, 'Layanan QRIS sedang tidak dapat dihubungi. Silakan coba beberapa saat lagi.');
        }

        $invoice = $response->json();
        $recoveredInvoice = false;
        if ($response->status() === 409 && ($invoice['error_code'] ?? null) === 'DUPLICATE_ERROR') {
            $invoice = $this->findInvoiceByReference($secretKey, $reference);
            $recoveredInvoice = ! empty($invoice);
        }

        if (! $response->successful() && ! $recoveredInvoice) {
            Log::warning('Xendit invoice creation failed', [
                'tagihan_id' => $bill->id,
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
            ]);
            abort(422, 'Gagal membuat link QRIS. Silakan coba lagi beberapa saat.');
        }

        abort_if(empty($invoice['invoice_url']), 422, 'Xendit belum mengembalikan link pembayaran QRIS.');
        abort_if(
            (int) ($invoice['amount'] ?? $bill->total_dibayar) !== (int) $bill->total_dibayar,
            409,
            'Nominal invoice QRIS tidak sesuai. Hubungi admin BALIKOS agar pembayaran diperiksa.'
        );

        $this->persistInvoice($bill, $reference, $invoice);

        return DB::table('tagihans')->where('id', $bill->id)->first();
    }

    private function persistInvoice(object $bill, string $reference, array $invoice): void
    {
        DB::table('tagihans')->where('id', $bill->id)->update([
            'metode_pembayaran' => 'qris',
            'gateway_provider' => 'xendit',
            'gateway_reference' => $reference,
            'gateway_invoice_id' => $invoice['id'] ?? null,
            'gateway_invoice_url' => $invoice['invoice_url'] ?? null,
            'gateway_status' => $invoice['status'] ?? 'PENDING',
            'gateway_payload' => json_encode($invoice),
            'updated_at' => now(),
        ]);
    }

    private function findInvoiceByReference(string $secretKey, string $reference): ?array
    {
        try {
            $response = $this->xenditRequest($secretKey)
                ->get('https://api.xendit.co/v2/invoices', ['external_id' => $reference]);
        } catch (ConnectionException $exception) {
            Log::error('Xendit duplicate invoice lookup failed', [
                'reference' => $reference,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return collect($response->json())
            ->first(fn ($invoice) => ($invoice['external_id'] ?? null) === $reference);
    }

    private function xenditRequest(string $secretKey)
    {
        return Http::withBasicAuth($secretKey, '')
            ->acceptJson()
            ->timeout(20);
    }

    private function invoiceReference(object $bill): string
    {
        $environment = substr(hash('sha256', config('app.url').'|'.config('app.env')), 0, 10);

        return 'balikos-'.$environment.'-tagihan-'.$bill->id;
    }

    public function handleInvoiceWebhook(Request $request): array
    {
        $configuredToken = (string) config('services.xendit.webhook_token');
        if ($configuredToken !== '' && ! hash_equals($configuredToken, (string) $request->header('x-callback-token'))) {
            abort(401, 'Webhook token tidak valid.');
        }

        $payload = $request->json()->all() ?: $request->all();
        $status = strtoupper((string) ($payload['status'] ?? ''));
        $reference = $payload['external_id'] ?? null;
        $invoiceId = $payload['id'] ?? null;
        $paymentId = $payload['payment_id'] ?? null;
        $eventKey = 'xendit:invoice:'.($paymentId ?: $invoiceId ?: $reference).':'.$status;

        abort_if(! $reference && ! $invoiceId, 422, 'Payload webhook tidak memiliki referensi invoice.');

        return DB::transaction(function () use ($payload, $status, $reference, $invoiceId, $paymentId, $eventKey) {
            if (DB::table('payment_gateway_events')->where('event_key', $eventKey)->lockForUpdate()->exists()) {
                return ['message' => 'Webhook duplikat diabaikan.', 'duplicate' => true];
            }

            $billQuery = DB::table('tagihans')->lockForUpdate();
            if ($reference) {
                $billQuery->where('gateway_reference', $reference);
            } else {
                $billQuery->where('gateway_invoice_id', $invoiceId);
            }
            $bill = $billQuery->first();
            abort_if(! $bill, 404, 'Tagihan untuk webhook ini tidak ditemukan.');

            $eventId = DB::table('payment_gateway_events')->insertGetId([
                'provider' => 'xendit',
                'event_key' => $eventKey,
                'event_type' => 'invoice.'.$status,
                'gateway_reference' => $reference,
                'gateway_payment_id' => $paymentId,
                'tagihan_id' => $bill->id,
                'payload' => json_encode($payload),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $paidAmount = (int) ($payload['paid_amount'] ?? $payload['amount'] ?? 0);
            $update = [
                'gateway_invoice_id' => $invoiceId ?: $bill->gateway_invoice_id,
                'gateway_payment_id' => $paymentId ?: $bill->gateway_payment_id,
                'gateway_status' => $status ?: $bill->gateway_status,
                'gateway_paid_amount' => $paidAmount ?: $bill->gateway_paid_amount,
                'gateway_payload' => json_encode($payload),
                'updated_at' => now(),
            ];

            if ($status === 'PAID' && $bill->status !== 'lunas' && $paidAmount >= (int) $bill->total_dibayar) {
                $rentPaidNow = max(0, (int) $bill->nominal - (int) ($bill->nominal_terbayar ?? 0));
                $paymentDate = isset($payload['paid_at']) ? Carbon::parse($payload['paid_at'])->toDateString() : now()->toDateString();
                $update = array_merge($update, [
                    'status' => 'lunas',
                    'tanggal_bayar' => $paymentDate,
                    'metode_pembayaran' => 'qris',
                    'nominal_terbayar' => (int) $bill->nominal,
                    'tanggal_verifikasi' => now(),
                    'tanggal_konfirmasi' => now(),
                    'alasan_penolakan' => null,
                ]);
                $this->recordRentPayment($bill, $rentPaidNow, $paymentDate);
                $this->creditWalletOnce($bill);
            }

            DB::table('tagihans')->where('id', $bill->id)->update($update);

            return ['message' => 'Webhook diproses.', 'event_id' => $eventId, 'tagihan_id' => $bill->id];
        });
    }

    private function prepareBillForXendit(object $bill): object
    {
        $remaining = max(0, (int) $bill->nominal - (int) ($bill->nominal_terbayar ?? 0));
        $fee = $this->qrisFee($remaining);
        $total = $remaining + $fee;

        if ((int) $bill->biaya_platform !== $fee || (int) ($bill->total_dibayar ?? 0) !== $total || empty($bill->gateway_reference)) {
            DB::table('tagihans')->where('id', $bill->id)->update([
                'biaya_platform' => $fee,
                'total_dibayar' => $total,
                'metode_pembayaran' => 'qris',
                'gateway_provider' => 'xendit',
                'gateway_reference' => $bill->gateway_reference ?: $this->invoiceReference($bill),
                'updated_at' => now(),
            ]);

            $bill = DB::table('tagihans')->where('id', $bill->id)->first();
        }

        return $bill;
    }

    private function creditWalletOnce(object $bill): void
    {
        $wallet = DB::table('kos_wallets')->where('kos_id', $bill->kos_id)->lockForUpdate()->first();
        if (! $wallet) {
            $walletId = DB::table('kos_wallets')->insertGetId([
                'kos_id' => $bill->kos_id,
                'saldo_tersedia' => 0,
                'saldo_pending' => 0,
                'total_ditarik' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $wallet = DB::table('kos_wallets')->where('id', $walletId)->lockForUpdate()->first();
        }

        $amount = max(0, (int) $bill->nominal - (int) ($bill->nominal_terbayar ?? 0));
        DB::table('kos_wallets')->where('id', $wallet->id)->update([
            'saldo_tersedia' => DB::raw('saldo_tersedia + '.$amount),
            'updated_at' => now(),
        ]);
    }

    private function recordRentPayment(object $bill, int $amount, string $paymentDate): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::table('tagihan_pembayarans')->insert([
            'tagihan_id' => $bill->id,
            'kos_id' => $bill->kos_id,
            'penghuni_id' => $bill->penghuni_id,
            'nominal' => $amount,
            'tanggal_bayar' => $paymentDate,
            'metode_pembayaran' => 'qris',
            'sumber' => 'xendit',
            'catatan' => 'Pembayaran QRIS otomatis.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function qrisFee(int $nominal): int
    {
        return (int) ceil($nominal * (float) config('services.xendit.qris_fee_rate', 0.009));
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (! $digits) {
            return null;
        }

        if (Str::startsWith($digits, '0')) {
            return '+62'.substr($digits, 1);
        }

        if (Str::startsWith($digits, '62')) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }
}
