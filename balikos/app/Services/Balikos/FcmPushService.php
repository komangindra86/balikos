<?php

namespace App\Services\Balikos;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FcmPushService
{
    public function sendToUser(int $userId, string $title, string $body, array $data = []): int
    {
        $credentials = $this->credentials();
        if (! $credentials) {
            Log::warning('Firebase push dilewati karena kredensial belum dikonfigurasi.');

            return 0;
        }

        $tokens = DB::table('push_notification_tokens')
            ->where('user_id', $userId)
            ->where('provider', 'fcm')
            ->get(['id', 'token']);
        if ($tokens->isEmpty()) {
            return 0;
        }

        try {
            $accessToken = $this->accessToken($credentials);
        } catch (Throwable $exception) {
            Log::warning('Firebase access token gagal dibuat.', ['message' => $exception->getMessage()]);

            return 0;
        }

        $sent = 0;
        foreach ($tokens as $token) {
            $sent += $this->sendToken(
                $token,
                $userId,
                $credentials['project_id'],
                $accessToken,
                $title,
                $body,
                $data
            );
        }

        return $sent;
    }

    private function sendToken(
        object $token,
        int $userId,
        string $projectId,
        string $accessToken,
        string $title,
        string $body,
        array $data
    ): int {
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $payload = [
            'message' => [
                'token' => $token->token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => collect($data)->map(fn ($value) => (string) $value)->all(),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'payments',
                        'sound' => 'default',
                        'color' => '#0a63c7',
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withToken($accessToken)->acceptJson()->timeout(15)->post($url, $payload);
            $responseData = $response->json() ?: [];
            $errorStatus = data_get($responseData, 'error.status');
            $errorMessage = data_get($responseData, 'error.message');
            $isUnregistered = $response->status() === 404
                || $errorStatus === 'UNREGISTERED'
                || str_contains((string) $errorMessage, 'UNREGISTERED');

            DB::table('push_notification_deliveries')->insert([
                'push_notification_token_id' => $token->id,
                'user_id' => $userId,
                'receipt_id' => $response->successful() ? ($responseData['name'] ?? null) : null,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data),
                'status' => $response->successful() ? 'delivered' : 'failed',
                'error_code' => $response->successful() ? null : ($errorStatus ?: 'HTTP_'.$response->status()),
                'error_message' => $response->successful() ? null : $errorMessage,
                'sent_at' => now(),
                'checked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($isUnregistered) {
                DB::table('push_notification_tokens')->where('id', $token->id)->delete();
            }

            if (! $response->successful()) {
                Log::warning('FCM push gagal dikirim.', [
                    'status' => $response->status(),
                    'error' => $errorStatus,
                    'message' => $errorMessage,
                ]);

                return 0;
            }

            return 1;
        } catch (Throwable $exception) {
            Log::warning('FCM push request threw an exception.', ['message' => $exception->getMessage()]);

            return 0;
        }
    }

    private function credentials(): ?array
    {
        $path = (string) config('services.firebase.credentials');
        if ($path === '') {
            return null;
        }
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            $path = base_path($path);
        }
        if (! is_file($path)) {
            return null;
        }

        $credentials = json_decode((string) file_get_contents($path), true);
        if (! is_array($credentials)
            || empty($credentials['project_id'])
            || empty($credentials['client_email'])
            || empty($credentials['private_key'])) {
            return null;
        }

        return $credentials;
    }

    private function accessToken(array $credentials): string
    {
        return Cache::remember('balikos_firebase_access_token_'.$credentials['project_id'], 3300, function () use ($credentials) {
            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $unsigned = $header.'.'.$claims;
            $signature = '';
            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Service account Firebase tidak dapat menandatangani token.');
            }
            $assertion = $unsigned.'.'.$this->base64Url($signature);

            $response = Http::asForm()->timeout(15)->post(
                $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]
            );
            $response->throw();

            return (string) $response->json('access_token');
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
