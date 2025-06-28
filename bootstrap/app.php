<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function ($schedule) {
        $schedule->command('notifikasi:penitipan-h3-h')->dailyAt('06:00');;
        $schedule->command('barang:konversi-donasi')->dailyAt('06:00');
        $schedule->command('barang:mark-expired')->dailyAt('06:00');
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('api', HandleCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
