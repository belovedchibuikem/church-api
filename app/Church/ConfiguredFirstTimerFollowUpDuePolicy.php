<?php

namespace App\Church;

use App\Church\Contracts\FirstTimerFollowUpDuePolicy;
use App\Models\Church;
use App\Support\Authorization\ScopeReference;
use App\Support\Platform\PlatformConfigurationResolver;
use App\Support\Platform\PlatformContext;
use App\Support\Platform\PlatformKey;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use UnexpectedValueException;

class ConfiguredFirstTimerFollowUpDuePolicy implements FirstTimerFollowUpDuePolicy
{
    private const string ConfigurationKey = 'church.first_timer_follow_up_after_hours';

    public function __construct(
        private PlatformConfigurationResolver $configurationResolver,
        private Repository $config,
        private Application $application,
    ) {}

    public function dueAt(Church $church, CarbonInterface $registeredAt): CarbonImmutable
    {
        $fallback = $this->config->get(self::ConfigurationKey);
        $hours = $this->configurationResolver->resolve(
            new PlatformKey(self::ConfigurationKey),
            new PlatformContext(
                $this->application->environment(),
                new ScopeReference('church', $church->public_id),
            ),
            $fallback,
        );

        if (! is_int($hours) || $hours < 1) {
            throw new UnexpectedValueException('The first-timer follow-up interval must be a positive integer.');
        }

        return CarbonImmutable::instance($registeredAt)->utc()->addHours($hours);
    }
}
