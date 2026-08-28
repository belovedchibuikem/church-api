<?php

namespace App\Providers;

use App\AdvisoryAi\Contracts\AdvisoryAiProvider;
use App\AdvisoryAi\DisabledAdvisoryAiProvider;
use App\Church\ConfiguredFirstTimerFollowUpDuePolicy;
use App\Church\Contracts\FirstTimerFollowUpDuePolicy;
use App\Files\ConfiguredFileContentPolicy;
use App\Files\Contracts\FileContentPolicy;
use App\Files\Contracts\MalwareScanner;
use App\Files\PendingMalwareScanner;
use App\Finance\ConfiguredPaymentGovernancePolicy;
use App\Finance\ConfiguredWebhookVerifier;
use App\Finance\Contracts\PaymentGateway;
use App\Finance\Contracts\PaymentGovernancePolicy;
use App\Finance\Contracts\WebhookVerifier;
use App\Finance\DatabasePaymentGovernancePolicy;
use App\Finance\DenyAllPaymentGovernancePolicy;
use App\Finance\HostedCheckoutPaymentGateway;
use App\Finance\LocalManualPaymentGateway;
use App\Finance\NullPaymentGateway;
use App\Finance\RejectAllWebhookVerifier;
use App\Finance\ResolvesActivePaymentConfiguration;
use App\Media\MediaAttachableType;
use App\Privacy\Contracts\DataSubjectRequestExecutionPolicy;
use App\Privacy\ExportTypeDataSubjectRequestExecutionPolicy;
use App\Safeguarding\Contracts\RestrictedRecordAccessPolicy;
use App\Safeguarding\PendingRestrictedRecordAccessPolicy;
use App\Search\Contracts\SearchProvider;
use App\Search\DatabaseCatalogSearchProvider;
use App\Storage\Contracts\ObjectStorageConnectionValidator;
use App\Storage\Contracts\ObjectStorageDiskResolver;
use App\Storage\DatabaseObjectStorageDiskResolver;
use App\Storage\S3ObjectStorageConnectionValidator;
use App\Support\Authorization\Contracts\ScopeContainmentResolver;
use App\Support\Authorization\DatabaseScopeContainmentResolver;
use App\Support\Health\LaravelReadinessChecker;
use App\Support\Health\ReadinessChecker;
use App\Support\Kca\Contracts\KcaCertificationEligibilityPolicy;
use App\Support\Kca\GovernanceAwareKcaCertificationEligibilityPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AdvisoryAiProvider::class, DisabledAdvisoryAiProvider::class);
        $this->app->bind(RestrictedRecordAccessPolicy::class, PendingRestrictedRecordAccessPolicy::class);
        $this->app->bind(DataSubjectRequestExecutionPolicy::class, ExportTypeDataSubjectRequestExecutionPolicy::class);
        $this->app->bind(SearchProvider::class, DatabaseCatalogSearchProvider::class);
        $this->app->bind(
            \App\Support\Communication\Contracts\CommunicationDeliveryGateway::class,
            \App\Support\Communication\ConfiguredCommunicationDeliveryGateway::class,
        );
        $this->app->bind(
            KcaCertificationEligibilityPolicy::class,
            GovernanceAwareKcaCertificationEligibilityPolicy::class,
        );

        $this->app->bind(ResolvesActivePaymentConfiguration::class, ResolvesActivePaymentConfiguration::class);
        $this->app->bind(
            PaymentGovernancePolicy::class,
            function ($app) {
                $fallback = strtolower((string) config('finance.governance_mode', 'deny')) === 'deny'
                    ? new DenyAllPaymentGovernancePolicy
                    : $app->make(ConfiguredPaymentGovernancePolicy::class);

                return new DatabasePaymentGovernancePolicy(
                    $app->make(ResolvesActivePaymentConfiguration::class),
                    $fallback,
                );
            },
        );
        $this->app->bind(WebhookVerifier::class, function ($app) {
            $active = $app->make(ResolvesActivePaymentConfiguration::class)->active();

            return $active === null
                ? new RejectAllWebhookVerifier
                : $app->make(ConfiguredWebhookVerifier::class);
        });

        $this->app->bind(
            FirstTimerFollowUpDuePolicy::class,
            ConfiguredFirstTimerFollowUpDuePolicy::class,
        );

        $this->app->bind(FileContentPolicy::class, ConfiguredFileContentPolicy::class);
        $this->app->bind(MalwareScanner::class, PendingMalwareScanner::class);

        $this->app->bind(
            ObjectStorageConnectionValidator::class,
            S3ObjectStorageConnectionValidator::class,
        );

        $this->app->singleton(
            ObjectStorageDiskResolver::class,
            DatabaseObjectStorageDiskResolver::class,
        );

        $this->app->bind(ReadinessChecker::class, LaravelReadinessChecker::class);

        $this->app->bind(
            ScopeContainmentResolver::class,
            DatabaseScopeContainmentResolver::class,
        );

        $this->app->bind(PaymentGateway::class, function ($app) {
            $configuration = $app->make(ResolvesActivePaymentConfiguration::class)->active();
            if ($configuration !== null) {
                return new HostedCheckoutPaymentGateway($configuration);
            }

            return match (strtolower((string) config('finance.gateway', 'none'))) {
                'local_manual' => new LocalManualPaymentGateway,
                default => new NullPaymentGateway,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Relation::enforceMorphMap(MediaAttachableType::MAP);

        RateLimiter::for('public-api', function (Request $request): Limit {
            return Limit::perMinute((int) config('api.rate_limits.public_per_minute'))
                ->by($request->ip() ?? 'unknown');
        });

        RateLimiter::for('public-catalogue', function (Request $request): Limit {
            return Limit::perMinute((int) config('api.rate_limits.public_catalogue_per_minute'))
                ->by($request->ip() ?? 'unknown');
        });

        RateLimiter::for('public-certificate-verification', function (Request $request): Limit {
            return Limit::perMinute((int) config('api.rate_limits.certificate_verification_per_minute'))
                ->by($request->ip() ?? 'unknown');
        });

        RateLimiter::for('public-home-church-applications', function (Request $request): array {
            $ipAddress = $request->ip() ?? 'unknown';
            $contactEmail = Str::lower(trim((string) $request->input('contact_email', 'unknown')));
            $contactHash = hash_hmac('sha256', $contactEmail, (string) config('app.key'));

            return [
                Limit::perMinute((int) config('api.rate_limits.home_church_application_per_minute'))
                    ->by("home-church-application|ip|{$ipAddress}"),
                Limit::perHour((int) config('api.rate_limits.home_church_application_per_contact_per_hour'))
                    ->by("home-church-application|contact|{$contactHash}"),
            ];
        });

        RateLimiter::for('mobile-login', function (Request $request): array {
            $ipAddress = $request->ip() ?? 'unknown';
            $emailHash = hash_hmac(
                'sha256',
                Str::lower(trim((string) $request->input('email'))),
                (string) config('app.key'),
            );

            return [
                Limit::perMinute((int) config('api.rate_limits.mobile_login_per_minute'))
                    ->by("mobile-login|ip|{$ipAddress}"),
                Limit::perMinute((int) config('api.rate_limits.mobile_login_per_email_per_minute'))
                    ->by("mobile-login|email|{$emailHash}"),
            ];
        });

        RateLimiter::for('mobile-refresh', function (Request $request): Limit {
            $refreshHash = hash_hmac(
                'sha256',
                (string) $request->input('refresh_token'),
                (string) config('app.key'),
            );

            return Limit::perMinute((int) config('api.rate_limits.mobile_refresh_per_minute'))
                ->by('mobile-refresh|'.($request->ip() ?? 'unknown').'|'.$refreshHash);
        });

        RateLimiter::for('mfa-setup', function (Request $request): Limit {
            $principal = $request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown';

            return Limit::perHour((int) config('api.rate_limits.mfa_setup_per_hour'))
                ->by("mfa-setup|{$principal}");
        });

        RateLimiter::for('mfa-challenge', function (Request $request): array {
            $principal = $request->user()?->getAuthIdentifier() ?? 'anonymous';
            $ipAddress = $request->ip() ?? 'unknown';

            return [
                Limit::perMinute((int) config('api.rate_limits.mfa_challenge_per_minute'))
                    ->by("mfa-challenge|principal|{$principal}"),
                Limit::perMinute((int) config('api.rate_limits.mfa_challenge_per_minute'))
                    ->by("mfa-challenge|ip|{$ipAddress}"),
            ];
        });

        RateLimiter::for('admin-storage', function (Request $request): Limit {
            $principal = $request->user()?->getAuthIdentifier() ?? 'anonymous';
            $ipAddress = $request->ip() ?? 'unknown';

            return Limit::perHour((int) config('api.rate_limits.admin_storage_per_hour'))
                ->by("admin-storage|{$principal}|{$ipAddress}");
        });

        RateLimiter::for('browser-csrf', function (Request $request): Limit {
            return Limit::perMinute((int) config('api.rate_limits.browser_csrf_per_minute'))
                ->by('browser-csrf|'.($request->ip() ?? 'unknown'));
        });

        RateLimiter::for('browser-registration', function (Request $request): array {
            return $this->browserIdentityLimits(
                $request,
                'browser-registration',
                (int) config('api.rate_limits.browser_registration_per_hour'),
                true,
            );
        });

        RateLimiter::for('browser-login', function (Request $request): array {
            return $this->browserIdentityLimits(
                $request,
                'browser-login',
                (int) config('api.rate_limits.browser_login_per_minute'),
            );
        });

        RateLimiter::for('browser-verification', function (Request $request): array {
            $principal = $request->user('web')?->getAuthIdentifier() ?? 'anonymous';
            $ipAddress = $request->ip() ?? 'unknown';
            $maximumAttempts = (int) config('api.rate_limits.browser_verification_per_minute');

            return [
                Limit::perMinute($maximumAttempts)->by("browser-verification|user|{$principal}"),
                Limit::perMinute($maximumAttempts)->by("browser-verification|ip|{$ipAddress}"),
            ];
        });

        RateLimiter::for('browser-password-recovery', function (Request $request): array {
            return $this->browserIdentityLimits(
                $request,
                'browser-password-recovery',
                (int) config('api.rate_limits.browser_password_recovery_per_minute'),
            );
        });
    }

    /** @return array<int, Limit> */
    private function browserIdentityLimits(
        Request $request,
        string $operation,
        int $maximumAttempts,
        bool $perHour = false,
    ): array {
        $ipAddress = $request->ip() ?? 'unknown';
        $emailHash = hash_hmac(
            'sha256',
            Str::lower(trim((string) $request->input('email', 'unknown'))),
            (string) config('app.key'),
        );
        $ipLimit = $perHour ? Limit::perHour($maximumAttempts) : Limit::perMinute($maximumAttempts);
        $emailLimit = $perHour ? Limit::perHour($maximumAttempts) : Limit::perMinute($maximumAttempts);

        return [
            $ipLimit->by("{$operation}|ip|{$ipAddress}"),
            $emailLimit->by("{$operation}|email|{$emailHash}"),
        ];
    }
}
