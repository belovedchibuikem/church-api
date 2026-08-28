<?php

use App\Http\Middleware\AuthenticateMobileAccessToken;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureActiveBrowserSecuritySession;
use App\Http\Middleware\EnsureRecentMfa;
use App\Http\Middleware\EnsureVerifiedEmail;
use App\Http\Middleware\StartProtectedApiSession;
use App\Http\Middleware\ValidateProtectedApiCsrf;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::middleware('throttle:public-api')
        ->group(base_path('routes/api/v1/public.php'));

    Route::middleware([])->group(base_path('routes/api/v1/mobile-auth.php'));

    $protectedMiddleware = [
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartProtectedApiSession::class,
        ValidateProtectedApiCsrf::class,
        AuthenticateMobileAccessToken::class,
        EnsureActiveAccount::class,
        EnsureActiveBrowserSecuritySession::class,
        EnsureVerifiedEmail::class,
    ];

    Route::middleware($protectedMiddleware)->group(function (): void {
        Route::prefix('user')
            ->name('user.')
            ->group(base_path('routes/api/v1/user.php'));

        Route::prefix('admin')
            ->name('admin.')
            ->middleware(EnsureRecentMfa::class)
            ->group(base_path('routes/api/v1/admin.php'));
    });
});
