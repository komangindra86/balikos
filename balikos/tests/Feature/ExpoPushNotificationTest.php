<?php

namespace Tests\Feature;

use App\Services\Balikos\ExpoPushService;
use App\Services\Balikos\FcmPushService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpoPushNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_push_ticket_and_receipt_are_tracked(): void
    {
        $userId = (int) DB::table('users')->where('email', 'pemilik@balikos.test')->value('id');
        $token = 'ExpoPushToken['.Str::random(24).']';
        $tokenId = DB::table('push_notification_tokens')->insertGetId([
            'user_id' => $userId,
            'provider' => 'expo',
            'token' => $token,
            'device_name' => 'test-device',
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://exp.host/--/api/v2/push/send' => Http::response([
                'data' => [['status' => 'ok', 'id' => 'receipt-'.Str::random(12)]],
            ]),
            'https://exp.host/--/api/v2/push/getReceipts' => function ($request) {
                $receiptId = $request->data()['ids'][0];

                return Http::response(['data' => [$receiptId => ['status' => 'ok']]]);
            },
        ]);

        $service = app(ExpoPushService::class);
        $this->assertSame(1, $service->sendToUser($userId, 'Tes', 'Pesan tes', ['type' => 'test']));

        $delivery = DB::table('push_notification_deliveries')
            ->where('push_notification_token_id', $tokenId)
            ->first();
        $this->assertNotNull($delivery);
        $this->assertSame('sent', $delivery->status);

        DB::table('push_notification_deliveries')->where('id', $delivery->id)->update([
            'sent_at' => now()->subMinutes(2),
        ]);

        $this->assertSame(1, $service->checkReceipts());
        $this->assertDatabaseHas('push_notification_deliveries', [
            'id' => $delivery->id,
            'status' => 'delivered',
        ]);
    }

    public function test_invalid_device_token_is_removed_from_expo_ticket(): void
    {
        $userId = (int) DB::table('users')->where('email', 'pemilik@balikos.test')->value('id');
        $token = 'ExpoPushToken['.Str::random(24).']';
        DB::table('push_notification_tokens')->insert([
            'user_id' => $userId,
            'provider' => 'expo',
            'token' => $token,
            'device_name' => 'old-device',
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://exp.host/--/api/v2/push/send' => Http::response([
                'data' => [[
                    'status' => 'error',
                    'message' => 'Device is not registered.',
                    'details' => ['error' => 'DeviceNotRegistered'],
                ]],
            ]),
        ]);

        $this->assertSame(0, app(ExpoPushService::class)->sendToUser($userId, 'Tes', 'Pesan tes'));
        $this->assertDatabaseMissing('push_notification_tokens', ['token' => $token]);
    }

    public function test_owner_can_remove_their_device_token_on_logout_flow(): void
    {
        $login = $this->postJson('/api/balikos/login', [
            'email' => 'pemilik@balikos.test',
            'password' => 'password',
            'device_name' => 'push-test',
        ])->assertOk()->json();
        $token = 'ExpoPushToken['.Str::random(24).']';

        $this->withToken($login['token'])->postJson('/api/balikos/push-token', [
            'token' => $token,
            'provider' => 'expo',
            'device_name' => 'test-device',
        ])->assertOk();

        $this->withToken($login['token'])->deleteJson('/api/balikos/push-token', [
            'token' => $token,
        ])->assertOk();

        $this->assertDatabaseMissing('push_notification_tokens', ['token' => $token]);
    }

    public function test_fcm_device_token_can_receive_a_notification(): void
    {
        $userId = (int) DB::table('users')->where('email', 'pemilik@balikos.test')->value('id');
        $token = 'fcm-'.Str::random(100);
        DB::table('push_notification_tokens')->insert([
            'user_id' => $userId,
            'provider' => 'fcm',
            'token' => $token,
            'device_name' => 'android-test',
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $opensslConfig = 'C:\\laragon\\bin\\php\\php-8.2.27-Win32-vs16-x64\\extras\\ssl\\openssl.cnf';
        $opensslOptions = ['private_key_bits' => 2048];
        if (is_file($opensslConfig)) {
            $opensslOptions['config'] = $opensslConfig;
        }
        $privateKey = openssl_pkey_new($opensslOptions);
        $this->assertNotFalse($privateKey);
        openssl_pkey_export($privateKey, $privateKeyPem, null, $opensslOptions);
        $credentialsPath = storage_path('framework/testing/firebase-service-account.json');
        if (! is_dir(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0777, true);
        }
        file_put_contents($credentialsPath, json_encode([
            'project_id' => 'balikos-test',
            'client_email' => 'firebase-test@balikos-test.iam.gserviceaccount.com',
            'private_key' => $privateKeyPem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.firebase.credentials' => $credentialsPath]);
        Cache::forget('balikos_firebase_access_token_balikos-test');

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token']),
            'https://fcm.googleapis.com/v1/projects/balikos-test/messages:send' => Http::response([
                'name' => 'projects/balikos-test/messages/'.Str::random(12),
            ]),
        ]);

        try {
            $this->assertSame(1, app(FcmPushService::class)->sendToUser(
                $userId,
                'Pembayaran baru',
                'Ada pembayaran yang perlu dicek.',
                ['type' => 'payment_proof', 'tagihan_id' => 10]
            ));
            $this->assertDatabaseHas('push_notification_deliveries', [
                'user_id' => $userId,
                'status' => 'delivered',
                'title' => 'Pembayaran baru',
            ]);
            Http::assertSent(fn ($request) => str_contains($request->url(), '/messages:send')
                && data_get($request->data(), 'message.token') === $token
                && data_get($request->data(), 'message.android.notification.channel_id') === 'payments');
        } finally {
            @unlink($credentialsPath);
        }
    }

    public function test_owner_can_send_a_notification_test_to_their_device(): void
    {
        $login = $this->postJson('/api/balikos/login', [
            'email' => 'pemilik@balikos.test',
            'password' => 'password',
            'device_name' => 'push-endpoint-test',
        ])->assertOk()->json();
        $token = 'ExpoPushToken['.Str::random(24).']';

        $this->withToken($login['token'])->postJson('/api/balikos/push-token', [
            'token' => $token,
            'provider' => 'expo',
            'device_name' => 'test-device',
        ])->assertOk();

        Http::fake([
            'https://exp.host/--/api/v2/push/send' => Http::response([
                'data' => [['status' => 'ok', 'id' => 'receipt-'.Str::random(12)]],
            ]),
        ]);

        $this->withToken($login['token'])
            ->postJson('/api/balikos/push-token/test')
            ->assertOk()
            ->assertJsonPath('sent', 1);
    }
}
