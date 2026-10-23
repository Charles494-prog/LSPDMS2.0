<?php

use App\Http\Controllers\ItRecoveryController;
use Illuminate\Support\Facades\Route;

/*
 * Protected IT recovery (break-glass) surface. Loaded from bootstrap/app.php
 * inside the 'web' middleware group. Not linked from the normal UI. There is
 * deliberately no route that edits or deletes the IT audit log.
 */
Route::prefix('it-recovery')->name('it-recovery.')->group(function (): void {
    Route::middleware('it-recovery.session:guest')->group(function (): void {
        Route::get('/login', [ItRecoveryController::class, 'showLogin'])->name('login');
        Route::post('/login', [ItRecoveryController::class, 'login'])->name('login.attempt');
    });

    Route::middleware('it-recovery.session')->group(function (): void {
        Route::get('/', [ItRecoveryController::class, 'dashboard'])->name('dashboard');
        Route::post('/logout', [ItRecoveryController::class, 'logout'])->name('logout');
        Route::get('/audit', [ItRecoveryController::class, 'audit'])->name('audit');
        Route::post('/actions/disable-email-features', [ItRecoveryController::class, 'disableEmailFeatures'])
            ->name('actions.disable-email-features');
        Route::post('/actions/reset-password', [ItRecoveryController::class, 'resetPassword'])
            ->name('actions.reset-password');
        Route::post('/actions/reenable-account', [ItRecoveryController::class, 'reenableAccount'])
            ->name('actions.reenable-account');
    });
});
