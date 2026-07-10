<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('balikos:auto-generate-tagihan {--days=7}', function () {
    $days = (int) $this->option('days');
    $controller = app(\App\Http\Controllers\Api\BalikosApiController::class);
    $total = 0;

    DB::table('kos')->where('status', 'aktif')->orderBy('id')->chunk(100, function ($kosRows) use (&$total, $controller, $days) {
        foreach ($kosRows as $kos) {
            $total += $controller->autoGenerateForKos((int) $kos->id, $days);
        }
    });

    $this->info('Auto-generate tagihan selesai. Total tagihan dibuat/diperbarui: '.$total);
})->purpose('Generate tagihan otomatis ketika mendekati tanggal jatuh tempo penghuni.');
