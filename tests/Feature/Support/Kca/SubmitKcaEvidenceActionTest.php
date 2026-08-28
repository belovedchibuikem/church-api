<?php

namespace Tests\Feature\Support\Kca;

use App\Exceptions\KcaEvidenceOwnershipException;
use App\Exceptions\KcaIdempotencyConflictException;
use App\Exceptions\KcaMentorAssignmentException;
use App\Files\FileAssetClassification;
use App\Kca\KcaAssignmentState;
use App\Models\AuditEvent;
use App\Models\FileAsset;
use App\Models\KcaApplication;
use App\Models\KcaAssignment;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaEvidenceReview;
use App\Models\KcaEvidenceSubmission;
use App\Models\KcaMentorAssignment;
use App\Models\KcaYear;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Kca\ReviewKcaEvidenceAction;
use App\Support\Kca\SubmitKcaEvidenceAction;
use App\Support\Kca\TransitionKcaAssignmentAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class SubmitKcaEvidenceActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cross_enrollment_evidence_is_rejected(): void
    {
        [$assignment] = $this->evidenceContext();
        [, $otherEnrollment, $otherPerson, $otherActor, $otherFile] = $this->evidenceContext();

        try {
            $this->app->make(SubmitKcaEvidenceAction::class)->handle(
                $assignment,
                $otherEnrollment,
                $otherFile,
                $otherPerson,
                'mobile-retry-1',
                $otherActor,
            );
            $this->fail('Expected evidence ownership denial.');
        } catch (KcaEvidenceOwnershipException) {
            $this->assertSame(0, KcaEvidenceSubmission::query()->count());
            $this->assertSame(KcaAssignmentState::Assigned, $assignment->refresh()->state);
            $this->assertSame(0, AuditEvent::query()->count());
        }
    }

    public function test_duplicate_mobile_retry_returns_the_original_evidence_without_duplicate_audit(): void
    {
        [$assignment, $enrollment, $person, $actor, $file] = $this->evidenceContext();
        $action = $this->app->make(SubmitKcaEvidenceAction::class);

        $first = $action->handle($assignment, $enrollment, $file, $person, 'mobile-retry-2', $actor);
        $second = $action->handle($assignment, $enrollment, $file, $person, 'mobile-retry-2', $actor);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, KcaEvidenceSubmission::query()->count());
        $this->assertSame(KcaAssignmentState::Submitted, $assignment->refresh()->state);
        $this->assertArrayNotHasKey('idempotency_key_hash', $first->toArray());
        $this->assertSame(1, AuditEvent::query()->where('action', 'kca.evidence.submitted')->count());
    }

    public function test_reusing_evidence_idempotency_key_for_another_file_is_rejected(): void
    {
        [$assignment, $enrollment, $person, $actor, $file] = $this->evidenceContext();
        $otherFile = $this->evidenceFile($person);
        $action = $this->app->make(SubmitKcaEvidenceAction::class);
        $action->handle($assignment, $enrollment, $file, $person, 'mobile-retry-3', $actor);

        $this->expectException(KcaIdempotencyConflictException::class);

        $action->handle($assignment, $enrollment, $otherFile, $person, 'mobile-retry-3', $actor);
    }

    public function test_current_assigned_mentor_can_review_available_evidence(): void
    {
        [$assignment, $enrollment, $person, $studentActor, $file] = $this->evidenceContext();
        $evidence = $this->app->make(SubmitKcaEvidenceAction::class)->handle(
            $assignment,
            $enrollment,
            $file,
            $person,
            'mobile-retry-4',
            $studentActor,
        );
        $this->app->make(TransitionKcaAssignmentAction::class)
            ->handle($assignment->refresh(), KcaAssignmentState::MentorReview, $studentActor);
        $mentor = Person::factory()->create();
        $mentorActor = User::factory()->for($mentor, 'person')->create();
        KcaMentorAssignment::factory()
            ->for($enrollment, 'enrollment')
            ->for($mentor, 'mentor')
            ->create();

        $review = $this->app->make(ReviewKcaEvidenceAction::class)->handle(
            $evidence,
            $mentor,
            KcaAssignmentState::Approved,
            $mentorActor,
        );

        $this->assertSame(KcaAssignmentState::Approved, $review->outcome);
        $this->assertSame(KcaAssignmentState::Approved, $assignment->refresh()->state);
        $this->assertSame(1, KcaEvidenceReview::query()->count());
    }

    public function test_unassigned_mentor_cannot_review_evidence(): void
    {
        [$assignment, $enrollment, $person, $studentActor, $file] = $this->evidenceContext();
        $evidence = $this->app->make(SubmitKcaEvidenceAction::class)->handle(
            $assignment,
            $enrollment,
            $file,
            $person,
            'mobile-retry-5',
            $studentActor,
        );
        $this->app->make(TransitionKcaAssignmentAction::class)
            ->handle($assignment->refresh(), KcaAssignmentState::MentorReview, $studentActor);
        $unassignedMentor = Person::factory()->create();
        $mentorActor = User::factory()->for($unassignedMentor, 'person')->create();

        $this->expectException(KcaMentorAssignmentException::class);

        $this->app->make(ReviewKcaEvidenceAction::class)->handle(
            $evidence,
            $unassignedMentor,
            KcaAssignmentState::Approved,
            $mentorActor,
        );
    }

    public function test_audit_failure_rolls_back_evidence_and_assignment_state(): void
    {
        [$assignment, $enrollment, $person, $actor, $file] = $this->evidenceContext();
        $this->mock(RecordAuditEventAction::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('audit unavailable'));

        try {
            $this->app->make(SubmitKcaEvidenceAction::class)->handle(
                $assignment,
                $enrollment,
                $file,
                $person,
                'mobile-retry-6',
                $actor,
            );
            $this->fail('Expected the audit exception.');
        } catch (RuntimeException) {
            $this->assertSame(0, KcaEvidenceSubmission::query()->count());
            $this->assertSame(KcaAssignmentState::Assigned, $assignment->refresh()->state);
        }
    }

    /**
     * @return array{KcaAssignment, KcaEnrollment, Person, User, FileAsset}
     */
    private function evidenceContext(): array
    {
        $person = Person::factory()->create();
        $actor = User::factory()->for($person, 'person')->create();
        $application = KcaApplication::factory()->accepted()->for($person)->create();
        $year = KcaYear::factory()->create();
        $cohort = KcaCohort::factory()->for($year, 'year')->create();
        $enrollment = KcaEnrollment::factory()
            ->for($application, 'application')
            ->for($person)
            ->for($year, 'year')
            ->for($cohort, 'cohort')
            ->create();
        $assignment = KcaAssignment::factory()
            ->for($enrollment, 'enrollment')
            ->inState(KcaAssignmentState::Assigned)
            ->create();

        return [$assignment, $enrollment, $person, $actor, $this->evidenceFile($person)];
    }

    private function evidenceFile(Person $person): FileAsset
    {
        return FileAsset::factory()
            ->available()
            ->for($person, 'owner')
            ->create([
                'purpose' => 'kca.evidence',
                'classification' => FileAssetClassification::Confidential,
            ]);
    }
}
