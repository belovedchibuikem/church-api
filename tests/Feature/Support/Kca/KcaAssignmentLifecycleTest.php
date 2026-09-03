<?php

namespace Tests\Feature\Support\Kca;

use App\Exceptions\KcaInvalidTransitionException;
use App\Kca\KcaAssignmentState;
use App\Models\AuditEvent;
use App\Models\KcaAssignment;
use App\Models\User;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Kca\TransitionKcaAssignmentAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class KcaAssignmentLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_assignment_follows_the_approved_review_and_final_assessment_path(): void
    {
        $assignment = KcaAssignment::factory()->create();
        $actor = User::factory()->create();
        $action = $this->app->make(TransitionKcaAssignmentAction::class);

        foreach ([
            KcaAssignmentState::Assigned,
            KcaAssignmentState::Submitted,
            KcaAssignmentState::MentorReview,
            KcaAssignmentState::Approved,
            KcaAssignmentState::AdminReview,
            KcaAssignmentState::FinalAssessment,
        ] as $state) {
            $assignment = $action->handle($assignment, $state, $actor);
        }

        $this->assertSame(KcaAssignmentState::FinalAssessment, $assignment->state);
        $this->assertNotNull($assignment->assigned_at);
        $this->assertNotNull($assignment->submitted_at);
        $this->assertNotNull($assignment->mentor_reviewed_at);
        $this->assertNotNull($assignment->admin_reviewed_at);
        $this->assertNotNull($assignment->final_assessed_at);
        $this->assertSame(6, AuditEvent::query()->where('action', 'kca.assignment.transitioned')->count());
    }

    public function test_assignment_rejects_an_invalid_state_transition(): void
    {
        $assignment = KcaAssignment::factory()->create();
        $actor = User::factory()->create();

        try {
            $this->app->make(TransitionKcaAssignmentAction::class)
                ->handle($assignment, KcaAssignmentState::FinalAssessment, $actor);
            $this->fail('Expected an invalid transition exception.');
        } catch (KcaInvalidTransitionException) {
            $this->assertSame(KcaAssignmentState::Draft, $assignment->refresh()->state);
            $this->assertSame(0, AuditEvent::query()->count());
        }
    }

    public function test_audit_failure_rolls_back_assignment_transition(): void
    {
        $assignment = KcaAssignment::factory()->create();
        $actor = User::factory()->create();
        $this->mock(RecordAuditEventAction::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('audit unavailable'));

        try {
            $this->app->make(TransitionKcaAssignmentAction::class)
                ->handle($assignment, KcaAssignmentState::Assigned, $actor);
            $this->fail('Expected the audit exception.');
        } catch (RuntimeException) {
            $this->assertSame(KcaAssignmentState::Draft, $assignment->refresh()->state);
            $this->assertNull($assignment->assigned_at);
        }
    }

    public function test_assignment_title_and_due_date_can_be_updated(): void
    {
        $assignment = KcaAssignment::factory()->create([
            'title' => 'Original title',
            'state' => KcaAssignmentState::Assigned,
        ]);
        $actor = User::factory()->create();

        $updated = $this->app->make(\App\Support\Kca\UpdateKcaAssignmentAction::class)->handle(
            $assignment,
            $actor,
            'Updated reflection essay',
            \Carbon\CarbonImmutable::parse('2026-09-30'),
        );

        $this->assertSame('Updated reflection essay', $updated->title);
        $this->assertSame('2026-09-30', $updated->due_at?->toDateString());
        $this->assertSame(1, AuditEvent::query()->where('action', 'kca.assignment.updated')->count());
    }
}
