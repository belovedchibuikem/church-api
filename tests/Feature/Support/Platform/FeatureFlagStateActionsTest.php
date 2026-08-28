<?php

namespace Tests\Feature\Support\Platform;

use App\Models\AuditEvent;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Platform\DisableFeatureFlagAction;
use App\Support\Platform\EnableFeatureFlagAction;
use App\Support\Platform\FeatureFlagResolver;
use App\Support\Platform\PlatformContext;
use App\Support\Platform\PlatformKey;
use App\Support\Platform\UpsertFeatureFlagAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class FeatureFlagStateActionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_enable_and_disable_are_audited_and_invalidate_cached_state(): void
    {
        $actor = User::factory()->create();
        $key = new PlatformKey('platform.features.state');
        $context = new PlatformContext('testing');
        $flag = $this->app->make(UpsertFeatureFlagAction::class)->handle(
            $key,
            $context,
            100,
            $actor,
        );
        $resolver = $this->app->make(FeatureFlagResolver::class);
        $this->assertFalse($resolver->enabled($key, $context));

        $enabledFlag = $this->app->make(EnableFeatureFlagAction::class)->handle($flag, $actor);
        $this->assertTrue($enabledFlag->is_enabled);
        $this->assertTrue($resolver->enabled($key, $context));

        $disabledFlag = $this->app->make(DisableFeatureFlagAction::class)->handle($enabledFlag, $actor);
        $this->assertFalse($disabledFlag->is_enabled);
        $this->assertFalse($resolver->enabled($key, $context));
        $this->assertSame([
            'platform.feature_flag.created',
            'platform.feature_flag.enabled',
            'platform.feature_flag.disabled',
        ], AuditEvent::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_repeating_the_same_flag_state_is_idempotent(): void
    {
        $actor = User::factory()->create();
        $flag = FeatureFlag::factory()->enabled()->create();
        $action = $this->app->make(EnableFeatureFlagAction::class);

        $first = $action->handle($flag, $actor);
        $second = $action->handle($first, $actor);

        $this->assertTrue($second->is_enabled);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_rolls_back_flag_state_when_audit_recording_fails(): void
    {
        $actor = User::factory()->create();
        $flag = FeatureFlag::factory()->create();
        $this->mock(RecordAuditEventAction::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('Audit unavailable'));
        $wasRolledBack = false;

        try {
            $this->app->make(EnableFeatureFlagAction::class)->handle($flag, $actor);
            $this->fail('Expected the failed audit to roll back the flag state.');
        } catch (RuntimeException) {
            $wasRolledBack = true;
        }

        $this->assertTrue($wasRolledBack);
        $this->assertFalse($flag->fresh()->is_enabled);
    }
}
