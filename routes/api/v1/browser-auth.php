<?php

use App\Http\Controllers\Api\V1\Auth\CsrfCookieController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MfaChallengeController;
use App\Http\Controllers\Api\V1\Auth\MfaTotpEnrollmentController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\SendEmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::get('csrf-cookie', CsrfCookieController::class)
        ->middleware('throttle:browser-csrf')
        ->name('csrf_cookie');

    Route::post('register', RegisterController::class)
        ->middleware('throttle:browser-registration')
        ->name('register');

    Route::post('login', LoginController::class)
        ->middleware('throttle:browser-login')
        ->name('login');

    Route::post('password/forgot', ForgotPasswordController::class)
        ->middleware('throttle:browser-password-recovery')
        ->name('password.forgot');

    Route::post('password/reset', ResetPasswordController::class)
        ->middleware('throttle:browser-password-recovery')
        ->name('password.reset');

    Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
        ->whereNumber('id')
        ->where('hash', '[a-f0-9]{40}')
        ->middleware(['signed', 'throttle:browser-verification'])
        ->name('verification.verify');

    Route::post('logout', LogoutController::class)
        ->middleware('auth:web')
        ->name('logout');

    Route::middleware(['auth:web', 'active.account', 'active.browser.session'])
        ->group(function (): void {
            Route::post('email/verification-notification', SendEmailVerificationController::class)
                ->middleware('throttle:browser-verification')
                ->name('verification.send');

            Route::post('mfa/totp/setup', [MfaTotpEnrollmentController::class, 'store'])
                ->middleware('throttle:mfa-setup')
                ->name('mfa.totp.setup');
            Route::post('mfa/totp/confirm', [MfaTotpEnrollmentController::class, 'confirm'])
                ->middleware('throttle:mfa-challenge')
                ->name('mfa.totp.confirm');
            Route::post('mfa/challenge', MfaChallengeController::class)
                ->middleware('throttle:mfa-challenge')
                ->name('mfa.challenge');
        });
});
