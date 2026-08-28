<?php

namespace Tests\Feature\Support\Platform;

use App\Models\AuditEvent;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Authorization\ScopeReference;
use App\Support\Platform\PlatformContext;
use App\Support\Platform\PlatformKey;
use App\Support\Platform\UpsertFeatureFlagAction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class UpsertFeatureFlagActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creates_a_disabled_scoped_flag_with_an_activation_window(): void
    {
        $this->travelTo('2026-09-01 00:00:00');
        $actor = User::factory()->create();
        $context = new PlatformContext(
            'production',
            new ScopeReference('church', '01JCHURCH00000000000000000'),
        );

        $flag = $this->app->make(UpsertFeatureFlagAction::class)->handle(
            new PlatformKey('platform.features.new_dashboard'),
            $context,
            25,
            $actor,
            now()->addDay(),
            now()->addDays(8),
        );

        $this->assertModelExists($flag);
        $this->assertFalse($flag->is_enabled);
        $this->assertSame(25, $flag->rollout_percentage);
        $this->assertSame('church', $flag->scope_type);
        $this->assertSame('01JCHURCH00000000000000000', $flag->scope_key);
        $this->assertTrue($flag->starts_at->equalTo(now()->addDay()));
        $this->assertTrue($flag->ends_at->equalTo(now()->addDays(8)));
        $this->assertSame($actor->getKey(), $flag->updated_by_user_id);
        $this->assertSame('platform.feature_flag.created', AuditEvent::query()->sole()->action);
    }

    #[DataProvider('invalidSchedules')]
    public function test_rejects_invalid_rollout_schedules_without_writing_records(
        int $percentage,
        string $startsAt,
        string $endsAt,
    ): void {
        $actor = User::factory()->create();
        $wasRejected = false;

        try {
            $this->app->make(UpsertFeatureFlagAction::class)->handle(
                new PlatformKey('platform.features.invalid'),
                new PlatformContext('testing'),
                $percentage,
                $actor,
                CarbonImmutable::parse($startsAt),
                CarbonImmutable::parse($endsAt),
            );
            $this->fail('Expected the invalid feature rollout schedule to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, FeatureFlag::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_rolls_back_feature_creation_when_audit_recording_fails(): void
    {
        $actor = User::factory()->create();
        $this->mock(RecordAuditEventAction::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('Audit unavailable'));
        $wasRolledBack = false;

        try {
            $this->app->make(UpsertFeatureFlagAction::class)->handle(
                new PlatformKey('platform.features.rollback'),
                new PlatformContext('testing'),
                100,
                $actor,
            );
            $this->fail('Expected the failed audit to roll back the feature flag.');
        } catch (RuntimeException) {
            $wasRolledBack = true;
        }

        $this->assertTrue($wasRolledBack);
        $this->assertSame(0, FeatureFlag::query()->count());
    }

    public function test_updates_an_existing_flag_and_records_the_update(): void
    {
        $actor = User::factory()->create();
        $key = new PlatformKey('platform.features.update');
        $context = new PlatformContext('testing');
        $action = $this->app->make(UpsertFeatureFlagAction::class);
        $createdFlag = $action->handle($key, $context, 10, $actor);

        $updatedFlag = $action->handle($key, $context, 75, $actor);

        $this->assertSame($createdFlag->getKey(), $updatedFlag->getKey());
        $this->assertSame(75, $updatedFlag->rollout_percentage);
        $this->assertSame(1, FeatureFlag::query()->count());
        $this->assertSame(
            ['platform.feature_flag.created', 'platform.feature_flag.updated'],
            AuditEvent::query()->orderBy('id')->pluck('action')->all(),
        );
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function invalidSchedules(): array
    {
        return [
            'negative percentage' => [-1, '2026-09-01', '2026-09-02'],
            'percentage over one hundred' => [101, '2026-09-01', '2026-09-02'],
            'end before start' => [50, '2026-09-02', '2026-09-01'],
            'equal window bounds' => [50, '2026-09-01', '2026-09-01'],
        ];

    }
}
