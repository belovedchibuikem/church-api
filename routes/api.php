<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::middleware('throttle:public-api')
        ->group(base_path('routes/api/v1/public.php'));

    Route::prefix('user')
        ->name('user.')
        ->group(base_path('routes/api/v1/user.php'));

    Route::prefix('admin')
        ->name('admin.')
        ->group(base_path('routes/api/v1/admin.php'));
});
