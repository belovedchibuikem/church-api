<?php

namespace Tests\Feature\Support\Platform;

use App\Models\User;
use App\Support\Authorization\ScopeReference;
use App\Support\Platform\EnableFeatureFlagAction;
use App\Support\Platform\FeatureFlagResolver;
use App\Support\Platform\FeatureRolloutKey;
use App\Support\Platform\PlatformContext;
use App\Support\Platform\PlatformKey;
use App\Support\Platform\UpsertFeatureFlagAction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class FeatureFlagResolverTest extends TestCase
{
    use DatabaseTransactions;

    public function test_activation_window_is_start_inclusive_and_end_exclusive(): void
    {
        $actor = User::factory()->create();
        $key = new PlatformKey('platform.features.window');
        $context = new PlatformContext('testing');
        $flag = $this->app->make(UpsertFeatureFlagAction::class)->handle(
            $key,
            $context,
            100,
            $actor,
            CarbonImmutable::parse('2026-09-10 10:00:00'),
            CarbonImmutable::parse('2026-09-10 11:00:00'),
        );
        $this->app->make(EnableFeatureFlagAction::class)->handle($flag, $actor);
        $resolver = $this->app->make(FeatureFlagResolver::class);

        $this->assertFalse($resolver->enabled($key, $context, at: CarbonImmutable::parse('2026-09-10 09:59:59')));
        $this->assertTrue($resolver->enabled($key, $context, at: CarbonImmutable::parse('2026-09-10 10:00:00')));
        $this->assertTrue($resolver->enabled($key, $context, at: CarbonImmutable::parse('2026-09-10 10:59:59')));
        $this->assertFalse($resolver->enabled($key, $context, at: CarbonImmutable::parse('2026-09-10 11:00:00')));
    }

    public function test_percentage_rollout_is_deterministic_for_opaque_keys(): void
    {
        $actor = User::factory()->create();
        $key = new PlatformKey('platform.features.rollout');
        $context = new PlatformContext('testing');
        $flag = $this->app->make(UpsertFeatureFlagAction::class)->handle(
            $key,
            $context,
            50,
            $actor,
        );
        $this->app->make(EnableFeatureFlagAction::class)->handle($flag, $actor);
        $resolver = $this->app->make(FeatureFlagResolver::class);
        $opaqueKeys = collect(range(1, 100))
            ->map(fn (int $number): FeatureRolloutKey => new FeatureRolloutKey(
                hash('sha256', 'opaque-subject-'.$number),
            ));

        $this->assertFalse($resolver->enabled($key, $context));

        $firstEvaluation = $opaqueKeys
            ->map(fn (FeatureRolloutKey $opaqueKey): bool => $resolver->enabled($key, $context, $opaqueKey))
            ->all();
        $secondEvaluation = $opaqueKeys
            ->map(fn (FeatureRolloutKey $opaqueKey): bool => $resolver->enabled($key, $context, $opaqueKey))
            ->all();

        $this->assertSame($firstEvaluation, $secondEvaluation);
        $this->assertContains(true, $firstEvaluation);
        $this->assertContains(false, $firstEvaluation);
    }

    public function test_exact_scope_flag_overrides_the_global_default(): void
    {
        $actor = User::factory()->create();
        $key = new PlatformKey('platform.features.scope');
        $churchScope = new ScopeReference('church', '01JCHURCH00000000000000000');
        $otherScope = new ScopeReference('church', '01JCHURCH00000000000000001');
        $action = $this->app->make(UpsertFeatureFlagAction::class);
        $globalFlag = $action->handle($key, new PlatformContext('*'), 100, $actor);
        $this->app->make(EnableFeatureFlagAction::class)->handle($globalFlag, $actor);
        $action->handle($key, new PlatformContext('production', $churchScope), 100, $actor);
        $resolver = $this->app->make(FeatureFlagResolver::class);

        $this->assertFalse($resolver->enabled($key, new PlatformContext('production', $churchScope)));
        $this->assertTrue($resolver->enabled($key, new PlatformContext('production', $otherScope)));
    }

    public function test_rejects_personal_data_as_a_rollout_key(): void
    {
        $wasRejected = false;

        try {
            new FeatureRolloutKey('person@example.test');
            $this->fail('Expected personal data to be rejected as a rollout key.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
    }
}
