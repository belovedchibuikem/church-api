<?php

use App\Http\Controllers\Api\V1\Auth\MfaChallengeController;
use App\Http\Controllers\Api\V1\Auth\MfaTotpEnrollmentController;
use App\Http\Controllers\Api\V1\Auth\MobileLoginController;
use App\Http\Controllers\Api\V1\Auth\MobileLogoutController;
use App\Http\Controllers\Api\V1\Auth\MobileRefreshController;
use App\Http\Controllers\Api\V1\Auth\MobileRegisterController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/auth')->name('mobile.auth.')->group(function (): void {
    Route::post('/register', MobileRegisterController::class)
        ->middleware('throttle:mobile-registration')
        ->name('register');
    Route::post('/login', MobileLoginController::class)
        ->middleware('throttle:mobile-login')
        ->name('login');
    Route::post('/refresh', MobileRefreshController::class)
        ->middleware('throttle:mobile-refresh')
        ->name('refresh');

    Route::middleware('auth.mobile')->group(function (): void {
        Route::post('/logout', MobileLogoutController::class)->name('logout');
        Route::post('/mfa/totp/setup', [MfaTotpEnrollmentController::class, 'store'])
            ->middleware('throttle:mfa-setup')
            ->name('mfa.totp.setup');
        Route::post('/mfa/totp/confirm', [MfaTotpEnrollmentController::class, 'confirm'])
            ->middleware('throttle:mfa-challenge')
            ->name('mfa.totp.confirm');
        Route::post('/mfa/challenge', MfaChallengeController::class)
            ->middleware('throttle:mfa-challenge')
            ->name('mfa.challenge');
    });
});
