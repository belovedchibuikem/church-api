<?php

namespace Tests\Feature\Support\Kca;

use App\Exceptions\KcaInvalidTransitionException;
use App\Kca\KcaApplicationState;
use App\Models\AuditEvent;
use App\Models\KcaAdmissionDecision;
use App\Models\KcaApplication;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaYear;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Kca\EnrollKcaStudentAction;
use App\Support\Kca\RecordKcaAdmissionDecisionAction;
use App\Support\Kca\TransitionKcaApplicationAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class KcaApplicationLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reviewed_accepted_application_enrolls_the_same_canonical_person(): void
    {
        $actor = User::factory()->create();
        $person = Person::factory()->create();
        $application = KcaApplication::factory()->for($person)->create();
        $year = KcaYear::factory()->create();
        $cohort = KcaCohort::factory()->for($year, 'year')->create();

        $reviewed = $this->app->make(TransitionKcaApplicationAction::class)
            ->handle($application, KcaApplicationState::Reviewed, $actor);
        $decision = $this->app->make(RecordKcaAdmissionDecisionAction::class)
            ->handle($reviewed, KcaApplicationState::Accepted, $actor);
        $enrollment = $this->app->make(EnrollKcaStudentAction::class)->handle(
            $reviewed,
            $cohort,
            'KCA-2026-0001',
            now(),
            $actor,
        );

        $this->assertSame(KcaApplicationState::Accepted, $reviewed->refresh()->status);
        $this->assertSame(KcaApplicationState::Accepted, $decision->outcome);
        $this->assertSame($person->getKey(), $enrollment->person_id);
        $this->assertSame($application->getKey(), $enrollment->kca_application_id);
        $this->assertSame($year->getKey(), $enrollment->kca_year_id);
        $this->assertSame(1, KcaAdmissionDecision::query()->count());
        $this->assertSame(1, KcaEnrollment::query()->count());
        $this->assertSame([
            'kca.application.reviewed',
            'kca.application.admission_decided',
            'kca.enrollment.created',
        ], AuditEvent::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_received_application_cannot_skip_review(): void
    {
        $application = KcaApplication::factory()->create();
        $actor = User::factory()->create();

        try {
            $this->app->make(TransitionKcaApplicationAction::class)
                ->handle($application, KcaApplicationState::Accepted, $actor);
            $this->fail('Expected an invalid transition exception.');
        } catch (KcaInvalidTransitionException) {
            $this->assertSame(KcaApplicationState::Received, $application->refresh()->status);
            $this->assertSame(0, KcaAdmissionDecision::query()->count());
            $this->assertSame(0, AuditEvent::query()->count());
        }
    }

    public function test_audit_failure_rolls_back_application_transition(): void
    {
        $application = KcaApplication::factory()->create();
        $actor = User::factory()->create();
        $this->mock(RecordAuditEventAction::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('audit unavailable'));

        try {
            $this->app->make(TransitionKcaApplicationAction::class)
                ->handle($application, KcaApplicationState::Reviewed, $actor);
            $this->fail('Expected the audit exception.');
        } catch (RuntimeException) {
            $this->assertSame(KcaApplicationState::Received, $application->refresh()->status);
            $this->assertNull($application->reviewed_at);
        }
    }
}
