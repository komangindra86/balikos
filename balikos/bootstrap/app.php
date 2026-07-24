<?php

use App\Http\Middleware\AuthenticateBalikos;
use App\Http\Middleware\AuthenticateBalikosApi;
use App\Http\Middleware\EnsureBalikosRole;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'balikos.auth' => AuthenticateBalikos::class,
            'balikos.role' => EnsureBalikosRole::class,
            'balikos.api' => AuthenticateBalikosApi::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('balikos:auto-generate-tagihan --days=7')->dailyAt('00:15')->withoutOverlapping();
        $schedule->command('balikos:check-push-receipts')->everyFiveMinutes()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
