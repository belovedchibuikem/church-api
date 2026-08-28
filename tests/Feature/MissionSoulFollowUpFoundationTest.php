<?php

namespace Tests\Feature;

use App\Exceptions\MissionAssignmentException;
use App\Exceptions\MissionIdempotencyConflictException;
use App\Exceptions\MissionInvalidTransitionException;
use App\Exceptions\MissionJourneyStateException;
use App\Exceptions\MissionSoulAlreadyLinkedException;
use App\Mission\Actions\AssignSoulMentorAction;
use App\Mission\Actions\CaptureMissionSoulAction;
use App\Mission\Actions\CompleteSoulFollowUpAction;
use App\Mission\Actions\RecordSoulFollowUpAction;
use App\Mission\Actions\TransitionMissionInvitationAction;
use App\Mission\Data\CaptureMissionSoulData;
use App\Mission\MissionInvitationStatus;
use App\Mission\MissionSoulJourneyStatus;
use App\Models\AuditEvent;
use App\Models\Crusade;
use App\Models\FollowUpInteraction;
use App\Models\MentorAssignment;
use App\Models\MissionInvitation;
use App\Models\MissionSoulJourney;
use App\Models\MissionTeamAssignment;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class MissionSoulFollowUpFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invitation_follows_the_exact_workflow_and_records_each_real_transition(): void
    {
        $this->freezeSecond();
        $actor = User::factory()->create();
        $invitation = MissionInvitation::factory()->for(Crusade::factory())->create();
        $action = $this->app->make(TransitionMissionInvitationAction::class);

        foreach ([
            MissionInvitationStatus::UnderReview,
            MissionInvitationStatus::Approved,
            MissionInvitationStatus::Planning,
            MissionInvitationStatus::Confirmed,
            MissionInvitationStatus::Completed,
        ] as $status) {
            $invitation = $action->handle($invitation, $status, 'workflow_advanced', $actor);
        }

        $sameCompletedInvitation = $action->handle(
            $invitation,
            MissionInvitationStatus::Completed,
            'workflow_advanced',
            $actor,
        );

        $this->assertSame(MissionInvitationStatus::Completed, $sameCompletedInvitation->status);
        $this->assertSame(5, AuditEvent::query()->where('action', 'mission.invitation.transitioned')->count());
        $this->assertEquals([
            'from_status' => 'confirmed',
            'to_status' => 'completed',
            'reason_code' => 'workflow_advanced',
        ], AuditEvent::query()->latest('id')->firstOrFail()->metadata);
    }

    public function test_invitation_rejects_skipped_or_reversed_transitions(): void
    {
        $invitation = MissionInvitation::factory()->create();
        $wasRejected = false;

        try {
            $this->app->make(TransitionMissionInvitationAction::class)->handle(
                $invitation,
                MissionInvitationStatus::Approved,
            );
            $this->fail('Expected a skipped invitation transition to be rejected.');
        } catch (MissionInvalidTransitionException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(MissionInvitationStatus::Received, $invitation->fresh()->status);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_captures_an_existing_canonical_person_idempotently_and_rejects_duplicate_linking(): void
    {
        $actor = User::factory()->create();
        $crusade = Crusade::factory()->create();
        $person = Person::factory()->withProfile()->create();
        $action = $this->app->make(CaptureMissionSoulAction::class);
        $data = new CaptureMissionSoulData(idempotencyKey: 'capture-existing-001', person: $person);

        $journey = $action->handle($crusade, $data, $actor);
        $sameJourney = $action->handle($crusade, $data, $actor);

        $this->assertSame($journey->getKey(), $sameJourney->getKey());
        $this->assertSame($person->getKey(), $journey->person_id);
        $this->assertSame(MissionSoulJourneyStatus::New, $journey->status);
        $this->assertTrue(Str::isUlid($journey->public_id));
        $this->assertSame(1, MissionSoulJourney::query()->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'mission.soul.captured')->count());

        $wasRejected = false;

        try {
            $action->handle(
                $crusade,
                new CaptureMissionSoulData(idempotencyKey: 'capture-existing-002', person: $person),
                $actor,
            );
            $this->fail('Expected a duplicate Person link to be rejected.');
        } catch (MissionSoulAlreadyLinkedException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame('MISSION_SOUL_ALREADY_LINKED', MissionSoulAlreadyLinkedException::ERROR_CODE);
        $this->assertSame(1, MissionSoulJourney::query()->count());
    }

    public function test_capture_can_create_one_canonical_person_and_rolls_everything_back_if_audit_fails(): void
    {
        $crusade = Crusade::factory()->create();
        $journey = $this->app->make(CaptureMissionSoulAction::class)->handle(
            $crusade,
            new CaptureMissionSoulData(
                idempotencyKey: 'capture-new-person-001',
                givenName: '  Ada  ',
                familyName: '  Okafor ',
                preferredName: ' Ada ',
            ),
        );

        $this->assertSame('Ada', $journey->person->profile->given_name);
        $this->assertSame('Okafor', $journey->person->profile->family_name);
        $this->assertSame(1, Person::query()->whereKey($journey->person_id)->count());

        $peopleBeforeFailure = Person::query()->count();
        $this->mock(RecordAuditEventAction::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('Audit persistence unavailable.'));
        $wasRolledBack = false;

        try {
            $this->app->make(CaptureMissionSoulAction::class)->handle(
                $crusade,
                new CaptureMissionSoulData(
                    idempotencyKey: 'capture-new-person-rollback',
                    givenName: 'Rollback',
                    familyName: 'Candidate',
                ),
            );
            $this->fail('Expected an audit failure to roll back the mission capture.');
        } catch (RuntimeException) {
            $wasRolledBack = true;
        }

        $this->assertTrue($wasRolledBack);
        $this->assertSame($peopleBeforeFailure, Person::query()->count());
        $this->assertSame(1, MissionSoulJourney::query()->count());
    }

    public function test_capture_idempotency_key_cannot_be_reused_for_different_people(): void
    {
        $crusade = Crusade::factory()->create();
        $firstPerson = Person::factory()->create();
        $secondPerson = Person::factory()->create();
        $action = $this->app->make(CaptureMissionSoulAction::class);
        $action->handle($crusade, new CaptureMissionSoulData('capture-conflict-001', $firstPerson));

        $this->expectException(MissionIdempotencyConflictException::class);
        $action->handle($crusade, new CaptureMissionSoulData('capture-conflict-001', $secondPerson));
    }

    public function test_assigns_a_same_crusade_team_member_as_mentor_idempotently(): void
    {
        [$journey, $teamAssignment] = $this->newJourneyAndMentorTeamAssignment();
        $actor = User::factory()->create();
        $action = $this->app->make(AssignSoulMentorAction::class);

        $assignment = $action->handle($journey, $teamAssignment, 'mentor-assignment-001', $actor);
        $sameAssignment = $action->handle($journey, $teamAssignment, 'mentor-assignment-001', $actor);

        $this->assertSame($assignment->getKey(), $sameAssignment->getKey());
        $this->assertTrue(Str::isUlid($assignment->public_id));
        $this->assertSame(MissionSoulJourneyStatus::MentorAssigned, $journey->fresh()->status);
        $this->assertSame(1, MentorAssignment::query()->count());
        $this->assertSame('mission.soul.mentor_assigned', AuditEvent::query()->sole()->action);
    }

    public function test_rejects_a_mentor_team_assignment_from_another_crusade(): void
    {
        $journey = MissionSoulJourney::factory()->create();
        $otherCrusadeAssignment = MissionTeamAssignment::factory()->create();
        $wasRejected = false;

        try {
            $this->app->make(AssignSoulMentorAction::class)->handle(
                $journey,
                $otherCrusadeAssignment,
                'cross-mission-mentor-001',
            );
            $this->fail('Expected a cross-crusade mentor assignment to be rejected.');
        } catch (MissionAssignmentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(MissionSoulJourneyStatus::New, $journey->fresh()->status);
        $this->assertSame(0, MentorAssignment::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_records_follow_up_idempotently_then_completes_it_with_safe_history(): void
    {
        [$journey, $teamAssignment] = $this->newJourneyAndMentorTeamAssignment();
        $mentorAssignment = $this->app->make(AssignSoulMentorAction::class)
            ->handle($journey, $teamAssignment, 'mentor-before-follow-up');
        $occurredAt = now()->subHour();
        $record = $this->app->make(RecordSoulFollowUpAction::class);

        $interaction = $record->handle(
            $journey,
            $mentorAssignment,
            'phone',
            'contacted',
            $occurredAt,
            'follow-up-record-001',
        );
        $sameInteraction = $record->handle(
            $journey,
            $mentorAssignment,
            'phone',
            'contacted',
            $occurredAt,
            'follow-up-record-001',
        );

        $this->assertSame($interaction->getKey(), $sameInteraction->getKey());
        $this->assertSame(1, FollowUpInteraction::query()->count());
        $this->assertSame(MissionSoulJourneyStatus::FollowUpActive, $journey->fresh()->status);

        $completed = $this->app->make(CompleteSoulFollowUpAction::class)
            ->handle($journey, 'discipleship_handoff');
        $sameCompleted = $this->app->make(CompleteSoulFollowUpAction::class)
            ->handle($journey, 'discipleship_handoff');

        $this->assertSame($completed->getKey(), $sameCompleted->getKey());
        $this->assertSame(MissionSoulJourneyStatus::FollowUpCompleted, $completed->status);
        $this->assertNotNull($completed->follow_up_completed_at);
        $this->assertSame([
            'mission.soul.mentor_assigned',
            'mission.soul.follow_up_recorded',
            'mission.soul.follow_up_completed',
        ], AuditEvent::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_rejects_cross_journey_or_closed_journey_follow_up(): void
    {
        [$firstJourney, $firstTeamAssignment] = $this->newJourneyAndMentorTeamAssignment();
        $firstMentor = $this->app->make(AssignSoulMentorAction::class)
            ->handle($firstJourney, $firstTeamAssignment, 'first-mentor-assignment');
        $secondJourney = MissionSoulJourney::factory()->mentorAssigned()->create();

        $crossJourneyRejected = false;

        try {
            $this->app->make(RecordSoulFollowUpAction::class)->handle(
                $secondJourney,
                $firstMentor,
                'phone',
                'contacted',
                now(),
                'cross-journey-follow-up',
            );
            $this->fail('Expected a cross-journey mentor assignment to be rejected.');
        } catch (MissionAssignmentException) {
            $crossJourneyRejected = true;
        }

        $closedJourney = MissionSoulJourney::factory()->closed()->create();
        $closedMentor = MentorAssignment::factory()->create([
            'mission_soul_journey_id' => $closedJourney,
        ]);
        $closedRejected = false;

        try {
            $this->app->make(RecordSoulFollowUpAction::class)->handle(
                $closedJourney,
                $closedMentor,
                'phone',
                'contacted',
                now(),
                'closed-journey-follow-up',
            );
            $this->fail('Expected follow-up on a closed journey to be rejected.');
        } catch (MissionJourneyStateException) {
            $closedRejected = true;
        }

        $this->assertTrue($crossJourneyRejected);
        $this->assertTrue($closedRejected);
        $this->assertSame(0, FollowUpInteraction::query()->count());
    }

    public function test_workflow_and_idempotency_fields_cannot_be_mass_assigned(): void
    {
        $journey = MissionSoulJourney::factory()->create();
        $wasRejected = false;

        try {
            $journey->fill([
                'status' => MissionSoulJourneyStatus::FollowUpCompleted,
                'capture_idempotency_scope_hash' => hash('sha256', 'attacker-key'),
                'closed_at' => now(),
            ]);
            $this->fail('Expected protected mission state to reject mass assignment.');
        } catch (MassAssignmentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertFalse($journey->isDirty('status'));
        $this->assertFalse($journey->isDirty('capture_idempotency_scope_hash'));
        $this->assertFalse($journey->isDirty('closed_at'));
        $this->assertArrayNotHasKey('capture_idempotency_scope_hash', $journey->toArray());
    }

    /** @return array{MissionSoulJourney, MissionTeamAssignment} */
    private function newJourneyAndMentorTeamAssignment(): array
    {
        $crusade = Crusade::factory()->create();
        $journey = MissionSoulJourney::factory()->for($crusade)->create();
        $teamAssignment = MissionTeamAssignment::factory()->for($crusade)->create();

        return [$journey, $teamAssignment];
    }
}
