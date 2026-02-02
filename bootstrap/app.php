<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'isAdmin' => \App\Shared\Middleware\UserAdminMiddleware::class,
            'adminOrCadete' => \App\Http\Middleware\AdminOrCadeteMiddleware::class,
            'cadete' => \App\Http\Middleware\CadeteMiddleware::class,
            'client.auth' => \App\Http\Middleware\ClientAuthMiddleware::class,
            'franchise' => \App\Http\Middleware\FranchiseMiddleware::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Notificar a cadetes cuando un comercio está por cerrar (cada 5 minutos)
        $schedule->command('cadetes:notify-location-closing')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No autenticado.',
                    'error' => 'Unauthenticated'
                ], 401);
            }
        });
    })->create();
