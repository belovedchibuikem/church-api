<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api/v1')
    ->name('api.v1.')
    ->group(base_path('routes/api/v1/browser-auth.php'));
