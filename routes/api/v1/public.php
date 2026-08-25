<?php

use App\Http\Controllers\Api\V1\Public\ApiStatusController;
use App\Http\Controllers\Api\V1\Public\HealthController;
use App\Http\Controllers\Api\V1\Public\ReadinessController;
use Illuminate\Support\Facades\Route;

Route::get('/', ApiStatusController::class)->name('status');
Route::get('/health', HealthController::class)->name('health');
Route::get('/health/readiness', ReadinessController::class)->name('health.readiness');
