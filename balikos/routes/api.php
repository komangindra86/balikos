<?php

use App\Http\Controllers\Api\BalikosApiController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::prefix('balikos')->group(function () {
    Route::post('/register', [BalikosApiController::class, 'register']);
    Route::post('/login', [BalikosApiController::class, 'login']);
    Route::post('/google-login', [BalikosApiController::class, 'googleLogin']);
    Route::get('/media/{path}', function (string $path) {
        abort_if(str_contains($path, '..') || ! Storage::disk('public')->exists($path), 404);

        $fullPath = Storage::disk('public')->path($path);
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';

        return response()->stream(function () use ($fullPath) {
            $handle = fopen($fullPath, 'rb');
            fpassthru($handle);
            fclose($handle);
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    })->where('path', '.*');
    Route::get('/portal/{portalToken}', [BalikosApiController::class, 'portalShow']);
    Route::post('/portal/{portalToken}/tagihan/{id}/bukti', [BalikosApiController::class, 'portalUploadBukti'])->whereNumber('id');

    Route::middleware('balikos.api')->group(function () {
        Route::post('/logout', [BalikosApiController::class, 'logout']);
        Route::get('/me', [BalikosApiController::class, 'me']);
        Route::post('/push-token', [BalikosApiController::class, 'pushTokenStore']);
        Route::get('/dashboard', [BalikosApiController::class, 'dashboard']);

        Route::get('/kos', [BalikosApiController::class, 'kosIndex']);
        Route::post('/kos', [BalikosApiController::class, 'kosStore']);
        Route::get('/kos/{id}', [BalikosApiController::class, 'kosShow'])->whereNumber('id');
        Route::put('/kos/{id}', [BalikosApiController::class, 'kosUpdate'])->whereNumber('id');
        Route::delete('/kos/{id}', [BalikosApiController::class, 'kosDelete'])->whereNumber('id');

        Route::get('/kamar', [BalikosApiController::class, 'kamarIndex']);
        Route::post('/kamar', [BalikosApiController::class, 'kamarStore']);
        Route::get('/kamar/{id}', [BalikosApiController::class, 'kamarShow'])->whereNumber('id');
        Route::put('/kamar/{id}', [BalikosApiController::class, 'kamarUpdate'])->whereNumber('id');
        Route::delete('/kamar/{id}', [BalikosApiController::class, 'kamarDelete'])->whereNumber('id');

        Route::get('/penghuni', [BalikosApiController::class, 'penghuniIndex']);
        Route::post('/penghuni', [BalikosApiController::class, 'penghuniStore']);
        Route::get('/penghuni/{id}', [BalikosApiController::class, 'penghuniShow'])->whereNumber('id');
        Route::put('/penghuni/{id}', [BalikosApiController::class, 'penghuniUpdate'])->whereNumber('id');
        Route::delete('/penghuni/{id}', [BalikosApiController::class, 'penghuniDelete'])->whereNumber('id');
        Route::get('/penghuni/{id}/portal-link', [BalikosApiController::class, 'portalLink'])->whereNumber('id');

        Route::get('/tagihan', [BalikosApiController::class, 'tagihanIndex']);
        Route::post('/tagihan/generate', [BalikosApiController::class, 'tagihanGenerate']);
        Route::post('/tagihan/auto-generate', [BalikosApiController::class, 'tagihanAutoGenerate']);
        Route::post('/tagihan/bayar-multi', [BalikosApiController::class, 'tagihanBayarMulti']);
        Route::get('/tagihan/{id}', [BalikosApiController::class, 'tagihanShow'])->whereNumber('id');
        Route::put('/tagihan/{id}/lunas', [BalikosApiController::class, 'tagihanLunas'])->whereNumber('id');
        Route::put('/tagihan/{id}/verifikasi', [BalikosApiController::class, 'tagihanVerifikasi'])->whereNumber('id');
        Route::put('/tagihan/{id}/tolak', [BalikosApiController::class, 'tagihanTolak'])->whereNumber('id');

        Route::get('/payment-methods', [BalikosApiController::class, 'paymentMethodIndex']);
        Route::post('/payment-methods', [BalikosApiController::class, 'paymentMethodStore']);
        Route::put('/payment-methods/{id}', [BalikosApiController::class, 'paymentMethodUpdate'])->whereNumber('id');
        Route::delete('/payment-methods/{id}', [BalikosApiController::class, 'paymentMethodDelete'])->whereNumber('id');
        Route::post('/wallet/withdraw', [BalikosApiController::class, 'walletWithdraw']);

        Route::get('/keuangan', [BalikosApiController::class, 'keuanganIndex']);
        Route::post('/keuangan', [BalikosApiController::class, 'keuanganStore']);
        Route::put('/keuangan/{id}', [BalikosApiController::class, 'keuanganUpdate'])->whereNumber('id');
        Route::delete('/keuangan/{id}', [BalikosApiController::class, 'keuanganDelete'])->whereNumber('id');

        Route::get('/pengumuman', [BalikosApiController::class, 'pengumumanIndex']);
        Route::post('/pengumuman', [BalikosApiController::class, 'pengumumanStore']);
        Route::put('/pengumuman/{id}', [BalikosApiController::class, 'pengumumanUpdate'])->whereNumber('id');
        Route::delete('/pengumuman/{id}', [BalikosApiController::class, 'pengumumanDelete'])->whereNumber('id');
    });
});
