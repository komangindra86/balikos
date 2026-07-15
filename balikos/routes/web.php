<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalikosController;
use App\Http\Controllers\BalikosPortalController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'app' => 'BALIKOS',
        'time' => now()->toIso8601String(),
    ]);
})->name('health');
Route::view('/privacy-policy', 'legal.privacy-policy')->name('privacy-policy');
Route::view('/account-deletion', 'legal.account-deletion')->name('account-deletion');
Route::get('/balikos/media/{path}', function (string $path) {
    abort_if(str_contains($path, '..') || ! Storage::disk('public')->exists($path), 404);

    return response()->file(Storage::disk('public')->path($path));
})->where('path', '.*')->name('balikos.media');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::get('/balikos/portal/{token}', [BalikosPortalController::class, 'show'])->name('balikos.portal.show');
Route::get('/balikos/portal/{token}/manifest.webmanifest', [BalikosPortalController::class, 'manifest'])->name('balikos.portal.manifest');
Route::get('/balikos/portal/{token}/status', [BalikosPortalController::class, 'status'])->name('balikos.portal.status');
Route::post('/balikos/portal/{token}/tagihan/{tagihan}/bukti', [BalikosPortalController::class, 'uploadProof'])
    ->whereNumber('tagihan')
    ->name('balikos.portal.upload-proof');

Route::middleware('balikos.auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('/dashboard/balikos')->name('balikos.')->group(function () {
        Route::get('/', [BalikosController::class, 'index'])->name('index');
        Route::get('/pemilik', [BalikosController::class, 'pemilik'])
            ->middleware('balikos.role:superadmin,admin_balikos')
            ->name('pemilik');
        Route::get('/pemilik/{id}', [BalikosController::class, 'showPemilik'])
            ->middleware('balikos.role:superadmin,admin_balikos')
            ->whereNumber('id')
            ->name('pemilik.show');
        Route::get('/kos', [BalikosController::class, 'kos'])->name('kos');
        Route::get('/indeks-harga', [BalikosController::class, 'indeksHarga'])->name('indeks-harga');
        Route::get('/laporan', [BalikosController::class, 'laporan'])
            ->middleware('balikos.role:superadmin,admin_balikos')
            ->name('laporan');
        Route::get('/penarikan', [BalikosController::class, 'penarikan'])
            ->middleware('balikos.role:superadmin,admin_balikos')
            ->name('penarikan');
        Route::put('/penarikan/{id}', [BalikosController::class, 'updatePenarikan'])
            ->middleware('balikos.role:superadmin,admin_balikos')
            ->whereNumber('id')
            ->name('penarikan.update');
    });
});
