<?php

use App\Http\Controllers\Api\BalikosApiController;
use App\Services\Balikos\ExpoPushService;
use App\Services\Balikos\PushNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('balikos:auto-generate-tagihan {--days=7}', function () {
    $days = (int) $this->option('days');
    $controller = app(BalikosApiController::class);
    $total = 0;

    $push = app(PushNotificationService::class);
    DB::table('kos')->where('status', 'aktif')->orderBy('id')->chunk(100, function ($kosRows) use (&$total, $controller, $days, $push) {
        foreach ($kosRows as $kos) {
            $created = $controller->autoGenerateForKos((int) $kos->id, $days);
            $total += $created;
            if ($created > 0) {
                $push->sendToUser(
                    (int) $kos->owner_id,
                    'Tagihan jatuh tempo dibuat',
                    $created.' tagihan sudah siap dicek dan dibagikan ke penghuni.',
                    ['type' => 'due_bills', 'kos_id' => (int) $kos->id]
                );
            }
        }
    });

    $this->info('Auto-generate tagihan selesai. Total tagihan dibuat/diperbarui: '.$total);
})->purpose('Generate tagihan otomatis ketika mendekati tanggal jatuh tempo penghuni.');

Artisan::command('balikos:check-push-receipts {--limit=1000}', function (ExpoPushService $push) {
    $checked = $push->checkReceipts((int) $this->option('limit'));
    $this->info("Receipt notifikasi diperiksa: {$checked}.");
})->purpose('Memeriksa status pengiriman push Expo dan membersihkan token perangkat tidak valid.');
