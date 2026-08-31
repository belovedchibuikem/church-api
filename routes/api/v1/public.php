<?php

use App\Http\Controllers\Api\V1\Public\ApiStatusController;
use App\Http\Controllers\Api\V1\Public\ChurchController;
use App\Http\Controllers\Api\V1\Public\ContentPageController;
use App\Http\Controllers\Api\V1\Public\HealthController;
use App\Http\Controllers\Api\V1\Public\HomeChurchApplicationController;
use App\Http\Controllers\Api\V1\Public\KcaLeadershipRecommendationController;
use App\Http\Controllers\Api\V1\Public\NativePaymentWebhookController;
use App\Http\Controllers\Api\V1\Public\PressPublicationController;
use App\Http\Controllers\Api\V1\Public\PublicBrandingController;
use App\Http\Controllers\Api\V1\Public\PublicCrusadeController;
use App\Http\Controllers\Api\V1\Public\PublicEventController;
use App\Http\Controllers\Api\V1\Public\PublicGeographyController;
use App\Http\Controllers\Api\V1\Public\PublicLivestreamController;
use App\Http\Controllers\Api\V1\Public\PublicMapsController;
use App\Http\Controllers\Api\V1\Public\PublicMediaController;
use App\Http\Controllers\Api\V1\Public\PublicMissionLocationController;
use App\Http\Controllers\Api\V1\Public\PublicPaymentConfigurationController;
use App\Http\Controllers\Api\V1\Public\PublicPaymentWebhookController;
use App\Http\Controllers\Api\V1\Public\ReadinessController;
use App\Http\Controllers\Api\V1\Public\VerifyKcaCertificateController;
use Illuminate\Support\Facades\Route;

Route::get('/', ApiStatusController::class)->name('status');
Route::get('/health', HealthController::class)->name('health');
Route::get('/health/readiness', ReadinessController::class)->name('health.readiness');

Route::prefix('geography')
    ->name('geography.')
    ->middleware('throttle:public-catalogue')
    ->group(function (): void {
        Route::get('/countries', [PublicGeographyController::class, 'countries'])->name('countries.index');
        Route::get('/countries/{country}', [PublicGeographyController::class, 'country'])->name('countries.show');
        Route::get('/countries/{country}/states', [PublicGeographyController::class, 'states'])->name('countries.states');
        Route::get('/countries/{country}/states/{state}/localities', [PublicGeographyController::class, 'localities'])
            ->name('countries.states.localities');
    });

Route::controller(ChurchController::class)
    ->prefix('churches')
    ->name('churches.')
    ->middleware('throttle:public-catalogue')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{church}', 'show')->whereUlid('church')->name('show');
    });

Route::post('/home-church-applications', HomeChurchApplicationController::class)
    ->middleware('throttle:public-home-church-applications')
    ->name('home_church_applications.store');

Route::prefix('press')
    ->name('press.')
    ->middleware('throttle:public-catalogue')
    ->group(function (): void {
        Route::get('/publications', [PressPublicationController::class, 'index'])
            ->name('publications.index');
        Route::get('/publications/{publicId}', [PressPublicationController::class, 'show'])
            ->whereUlid('publicId')
            ->name('publications.show');
        Route::get('/publications/{publicId}/download', [PressPublicationController::class, 'download'])
            ->whereUlid('publicId')
            ->name('publications.download');
    });

Route::controller(PublicEventController::class)
    ->prefix('events')
    ->name('events.')
    ->middleware('throttle:public-catalogue')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{event}', 'show')->whereUlid('event')->name('show');
    });

Route::prefix('livestreams')
    ->name('livestreams.')
    ->middleware('throttle:public-catalogue')
    ->group(function (): void {
        Route::get('/current', [PublicLivestreamController::class, 'current'])->name('current');
        Route::get('/{livestream}', [PublicLivestreamController::class, 'show'])
            ->whereUlid('livestream')
            ->name('show');
    });

Route::get('/kca/certificates/verify', VerifyKcaCertificateController::class)
    ->middleware('throttle:public-certificate-verification')
    ->name('kca.certificates.verify');

Route::get('/kca/recommendations/{token}', [KcaLeadershipRecommendationController::class, 'show'])
    ->where('token', '[A-Fa-f0-9]{64}')
    ->middleware('throttle:public-catalogue')
    ->name('kca.recommendations.show');
Route::post('/kca/recommendations/{token}', [KcaLeadershipRecommendationController::class, 'submit'])
    ->where('token', '[A-Fa-f0-9]{64}')
    ->middleware('throttle:public-catalogue')
    ->name('kca.recommendations.submit');

Route::prefix('mission')
    ->name('mission.')
    ->middleware('throttle:public-catalogue')
    ->group(function (): void {
        Route::get('/locations', PublicMissionLocationController::class)->name('locations.index');
        Route::get('/crusades', [PublicCrusadeController::class, 'index'])->name('crusades.index');
        Route::get('/crusades/{crusade}', [PublicCrusadeController::class, 'show'])
            ->whereUlid('crusade')
            ->name('crusades.show');
    });

Route::prefix('maps')
    ->name('maps.')
    ->middleware('throttle:public-catalogue')
    ->group(function (): void {
        Route::get('/configuration', [PublicMapsController::class, 'configuration'])->name('configuration.show');
        Route::get('/places', [PublicMapsController::class, 'places'])->name('places.index');
    });

Route::prefix('finance/webhooks')
    ->name('finance.webhooks.')
    ->middleware('throttle:public-catalogue')
    ->group(function (): void {
        Route::post('/reconcile', [PublicPaymentWebhookController::class, 'reconcile'])->name('reconcile');
        Route::post('/disputes', [PublicPaymentWebhookController::class, 'dispute'])->name('disputes');
        Route::post('/paystack', [NativePaymentWebhookController::class, 'paystack'])->name('paystack');
        Route::post('/flutterwave', [NativePaymentWebhookController::class, 'flutterwave'])->name('flutterwave');
        Route::post('/stripe', [NativePaymentWebhookController::class, 'stripe'])->name('stripe');
    });

Route::get('/payments/configuration', PublicPaymentConfigurationController::class)
    ->middleware('throttle:public-catalogue')
    ->name('payments.configuration.show');

Route::get('/branding', PublicBrandingController::class)
    ->middleware('throttle:public-catalogue')
    ->name('branding.show');

Route::get('/media/{file}', [PublicMediaController::class, 'show'])
    ->whereUlid('file')
    ->middleware('throttle:public-catalogue')
    ->name('media.show');

Route::prefix('content')
    ->name('content.')
    ->middleware('throttle:public-catalogue')
    ->group(function (): void {
        Route::get('/pages', [ContentPageController::class, 'index'])->name('pages.index');
        Route::get('/pages/{slug}', [ContentPageController::class, 'show'])
            ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
            ->name('pages.show');
    });
