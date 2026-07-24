<?php

namespace App\Services\Balikos;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpoPushService
{
    private const SEND_URL = 'https://exp.host/--/api/v2/push/send';

    private const RECEIPTS_URL = 'https://exp.host/--/api/v2/push/getReceipts';

    public function sendToUser(int $userId, string $title, string $body, array $data = []): int
    {
        $tokens = DB::table('push_notification_tokens')
            ->where('user_id', $userId)
            ->where('provider', 'expo')
            ->where(function ($query) {
                $query->where('token', 'like', 'ExponentPushToken[%')
                    ->orWhere('token', 'like', 'ExpoPushToken[%');
            })
            ->get(['id', 'token']);

        $sent = 0;
        foreach ($tokens->chunk(100) as $chunk) {
            $sent += $this->sendChunk($chunk, $userId, $title, $body, $data);
        }

        return $sent;
    }

    public function checkReceipts(int $limit = 1000): int
    {
        $deliveries = DB::table('push_notification_deliveries')
            ->where('status', 'sent')
            ->whereNotNull('receipt_id')
            ->whereNull('checked_at')
            ->where('sent_at', '<=', now()->subMinute())
            ->orderBy('id')
            ->limit(min(max($limit, 1), 1000))
            ->get();

        if ($deliveries->isEmpty()) {
            return 0;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->post(self::RECEIPTS_URL, ['ids' => $deliveries->pluck('receipt_id')->all()]);

            if (! $response->successful()) {
                Log::warning('Expo push receipt request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return 0;
            }

            $receipts = $response->json('data', []);
            foreach ($deliveries as $delivery) {
                $receipt = $receipts[$delivery->receipt_id] ?? null;
                if (! is_array($receipt)) {
                    continue;
                }

                $errorCode = data_get($receipt, 'details.error');
                DB::table('push_notification_deliveries')->where('id', $delivery->id)->update([
                    'status' => ($receipt['status'] ?? null) === 'ok' ? 'delivered' : 'failed',
                    'error_code' => $errorCode,
                    'error_message' => $receipt['message'] ?? null,
                    'checked_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($errorCode === 'DeviceNotRegistered' && $delivery->push_notification_token_id) {
                    DB::table('push_notification_tokens')
                        ->where('id', $delivery->push_notification_token_id)
                        ->delete();
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Expo push receipt request threw an exception.', [
                'message' => $exception->getMessage(),
            ]);

            return 0;
        }

        return $deliveries->count();
    }

    private function sendChunk(Collection $tokens, int $userId, string $title, string $body, array $data): int
    {
        $messages = $tokens->map(fn ($row) => [
            'to' => $row->token,
            'sound' => 'default',
            'channelId' => 'payments',
            'priority' => 'high',
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ])->values()->all();

        try {
            $response = Http::acceptJson()->timeout(15)->post(self::SEND_URL, $messages);
            if (! $response->successful()) {
                Log::warning('Expo push send request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $this->recordTransportFailure($tokens, $userId, $title, $body, $data, 'HTTP_'.$response->status());

                return 0;
            }

            $tickets = $response->json('data', []);
            $sent = 0;
            foreach ($tokens->values() as $index => $token) {
                $ticket = $tickets[$index] ?? [];
                $status = $ticket['status'] ?? 'error';
                $errorCode = data_get($ticket, 'details.error');
                $receiptId = $ticket['id'] ?? null;

                DB::table('push_notification_deliveries')->insert([
                    'push_notification_token_id' => $token->id,
                    'user_id' => $userId,
                    'receipt_id' => $receiptId,
                    'title' => $title,
                    'body' => $body,
                    'data' => json_encode($data),
                    'status' => $status === 'ok' ? 'sent' : 'failed',
                    'error_code' => $errorCode,
                    'error_message' => $ticket['message'] ?? null,
                    'sent_at' => now(),
                    'checked_at' => $status === 'ok' ? null : now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($status === 'ok') {
                    $sent++;
                } elseif ($errorCode === 'DeviceNotRegistered') {
                    DB::table('push_notification_tokens')->where('id', $token->id)->delete();
                }
            }

            return $sent;
        } catch (Throwable $exception) {
            Log::warning('Expo push send request threw an exception.', [
                'message' => $exception->getMessage(),
            ]);
            $this->recordTransportFailure($tokens, $userId, $title, $body, $data, 'CONNECTION_ERROR');

            return 0;
        }
    }

    private function recordTransportFailure(
        Collection $tokens,
        int $userId,
        string $title,
        string $body,
        array $data,
        string $errorCode
    ): void {
        $now = now();
        DB::table('push_notification_deliveries')->insert(
            $tokens->map(fn ($token) => [
                'push_notification_token_id' => $token->id,
                'user_id' => $userId,
                'receipt_id' => null,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data),
                'status' => 'failed',
                'error_code' => $errorCode,
                'error_message' => null,
                'sent_at' => $now,
                'checked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }
}
