<?php

use App\Http\Middleware\EnsureItRecoverySession;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserIsHeadSecretary;
use App\Http\Middleware\EnsureUserIsLoggedIn;
use App\Http\Middleware\PreventSensitiveCaching;
use App\Http\Middleware\RedirectAuthenticatedUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/it-recovery.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'login.session' => EnsureUserIsLoggedIn::class,
            'permission' => EnsureUserHasPermission::class,
            'head.secretary' => EnsureUserIsHeadSecretary::class,
            'guest.redirect' => RedirectAuthenticatedUser::class,
            'it-recovery.session' => EnsureItRecoverySession::class,
            'no-store' => PreventSensitiveCaching::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
