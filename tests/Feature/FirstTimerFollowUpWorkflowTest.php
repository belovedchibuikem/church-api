<?php

namespace Tests\Feature;

use App\Church\FollowUpTaskStatus;
use App\Church\FollowUpTaskType;
use App\Models\AuditEvent;
use App\Models\Church;
use App\Models\FirstTimer;
use App\Models\FollowUpTask;
use App\Models\HomeChurch;
use App\Models\Person;
use App\Models\User;
use App\Platform\ConfigurationClassification;
use App\Platform\ConfigurationValueType;
use App\Support\Authorization\ScopeReference;
use App\Support\Church\CompleteFollowUpTaskAction;
use App\Support\Church\RegisterFirstTimerAction;
use App\Support\Platform\PlatformContext;
use App\Support\Platform\PlatformKey;
use App\Support\Platform\UpsertPlatformConfigurationAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;
use UnexpectedValueException;

class FirstTimerFollowUpWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registration_creates_a_configured_follow_up_for_the_canonical_person(): void
    {
        $this->travelTo('2026-08-26 10:00:00 UTC');
        config()->set('church.first_timer_follow_up_after_hours', 6);
        [$church, $homeChurch] = $this->createChurchContext();
        $personCountBeforeRegistration = Person::query()->count();
        $person = Person::factory()->create();
        $assignedPerson = Person::factory()->create();
        $actor = User::factory()->create();

        $firstTimer = $this->app->make(RegisterFirstTimerAction::class)->handle(
            $person,
            $church,
            $homeChurch,
            $assignedPerson,
            actor: $actor,
        );
        $task = $firstTimer->followUpTasks->sole();

        $this->assertSame($person->getKey(), $firstTimer->person_id);
        $this->assertSame($church->getKey(), $firstTimer->church_id);
        $this->assertSame($homeChurch->getKey(), $firstTimer->home_church_id);
        $this->assertSame(FollowUpTaskType::FirstTimerContact, $task->type);
        $this->assertSame(FollowUpTaskStatus::Pending, $task->status);
        $this->assertSame($assignedPerson->getKey(), $task->assigned_to_person_id);
        $this->assertSame('2026-08-26T16:00:00+00:00', $task->due_at->toIso8601String());
        $this->assertSame($personCountBeforeRegistration + 2, Person::query()->count());

        $audits = AuditEvent::query()
            ->whereIn('action', ['church.first_timer.registered', 'church.follow_up.created'])
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $audits);
        $this->assertSame(['home_church', 'home_church'], $audits->pluck('scope_type')->all());
        $this->assertSame([$homeChurch->public_id, $homeChurch->public_id], $audits->pluck('scope_id')->all());
    }

    public function test_church_scoped_platform_configuration_overrides_the_fallback_interval(): void
    {
        $this->travelTo('2026-08-26 10:00:00 UTC');
        config()->set('church.first_timer_follow_up_after_hours', 24);
        [$church] = $this->createChurchContext();
        $actor = User::factory()->create();
        $context = new PlatformContext(
            $this->app->environment(),
            new ScopeReference('church', $church->public_id),
        );
        $this->app->make(UpsertPlatformConfigurationAction::class)->handle(
            new PlatformKey('church.first_timer_follow_up_after_hours'),
            ConfigurationValueType::Integer,
            ConfigurationClassification::Internal,
            3,
            $context,
            $actor,
        );

        $firstTimer = $this->app->make(RegisterFirstTimerAction::class)->handle(
            Person::factory()->create(),
            $church,
            actor: $actor,
        );

        $this->assertSame(
            '2026-08-26T13:00:00+00:00',
            $firstTimer->followUpTasks->sole()->due_at->toIso8601String(),
        );
    }

    public function test_rejects_duplicate_first_timer_registration_without_duplicate_follow_up(): void
    {
        [$church] = $this->createChurchContext();
        $person = Person::factory()->create();
        $action = $this->app->make(RegisterFirstTimerAction::class);
        $action->handle($person, $church);
        $auditCount = AuditEvent::query()->count();
        $wasRejected = false;

        try {
            $action->handle($person, $church);
            $this->fail('Expected duplicate first-timer registration to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(1, FirstTimer::query()->count());
        $this->assertSame(1, FollowUpTask::query()->count());
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    public function test_rejects_an_invalid_follow_up_configuration_without_partial_records(): void
    {
        config()->set('church.first_timer_follow_up_after_hours', 0);
        [$church] = $this->createChurchContext();
        $wasRejected = false;

        try {
            $this->app->make(RegisterFirstTimerAction::class)->handle(
                Person::factory()->create(),
                $church,
            );
            $this->fail('Expected the invalid follow-up configuration to be rejected.');
        } catch (UnexpectedValueException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, FirstTimer::query()->count());
        $this->assertSame(0, FollowUpTask::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_rejects_a_home_church_from_another_church(): void
    {
        [$church] = $this->createChurchContext();
        [, $foreignHomeChurch] = $this->createChurchContext();
        $wasRejected = false;

        try {
            $this->app->make(RegisterFirstTimerAction::class)->handle(
                Person::factory()->create(),
                $church,
                $foreignHomeChurch,
            );
            $this->fail('Expected cross-church first-timer registration to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, FirstTimer::query()->count());
        $this->assertSame(0, FollowUpTask::query()->count());
    }

    public function test_completing_follow_up_marks_the_first_timer_contacted_and_is_idempotent(): void
    {
        $this->travelTo('2026-08-26 10:00:00 UTC');
        [$church] = $this->createChurchContext();
        $actor = User::factory()->create();
        $firstTimer = $this->app->make(RegisterFirstTimerAction::class)->handle(
            Person::factory()->create(),
            $church,
            actor: $actor,
        );
        $task = $firstTimer->followUpTasks->sole();
        $action = $this->app->make(CompleteFollowUpTaskAction::class);

        $completed = $action->handle($task, 'contact_completed', $actor);
        $auditCount = AuditEvent::query()->count();
        $sameTask = $action->handle($completed, 'completion_retry', $actor);

        $this->assertSame($completed->getKey(), $sameTask->getKey());
        $this->assertSame(FollowUpTaskStatus::Completed, $sameTask->status);
        $this->assertSame('contact_completed', $sameTask->completion_reason_code);
        $this->assertSame('2026-08-26T10:00:00+00:00', $sameTask->completed_at->toIso8601String());
        $this->assertSame(
            '2026-08-26T10:00:00+00:00',
            $firstTimer->fresh()->contacted_at->toIso8601String(),
        );
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    /** @return array{Church, HomeChurch} */
    private function createChurchContext(): array
    {
        $church = Church::factory()->create();
        $homeChurch = HomeChurch::factory()->for($church)->create();

        return [$church, $homeChurch];
    }
}
