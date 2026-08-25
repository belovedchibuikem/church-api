<?php

namespace App\Providers;

use App\Storage\Contracts\ObjectStorageConnectionValidator;
use App\Storage\Contracts\ObjectStorageDiskResolver;
use App\Storage\DatabaseObjectStorageDiskResolver;
use App\Storage\S3ObjectStorageConnectionValidator;
use App\Support\Health\LaravelReadinessChecker;
use App\Support\Health\ReadinessChecker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            ObjectStorageConnectionValidator::class,
            S3ObjectStorageConnectionValidator::class,
        );

        $this->app->singleton(
            ObjectStorageDiskResolver::class,
            DatabaseObjectStorageDiskResolver::class,
        );

        $this->app->bind(ReadinessChecker::class, LaravelReadinessChecker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        RateLimiter::for('public-api', function (Request $request): Limit {
            return Limit::perMinute((int) config('api.rate_limits.public_per_minute'))
                ->by($request->ip() ?? 'unknown');
        });
    }
}
