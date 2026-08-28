<?php

namespace App\Support\Kca;

use App\Kca\KcaAssignmentState;
use App\Models\KcaAssignment;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class TransitionKcaAssignmentAction
{
    public function __construct(
        private KcaAssignmentTransitionService $transitions,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        KcaAssignment $assignment,
        KcaAssignmentState $to,
        User $actor,
    ): KcaAssignment {
        return DB::transaction(function () use ($assignment, $to, $actor): KcaAssignment {
            $lockedAssignment = KcaAssignment::query()
                ->lockForUpdate()
                ->findOrFail($assignment->getKey());
            $from = $lockedAssignment->state;

            $this->transitions->assertCanTransition($from, $to);
            $now = now()->utc();
            $lockedAssignment->state = $to;
            $lockedAssignment->last_transitioned_by_user_id = $actor->getKey();
            $this->recordTransitionTimestamp($lockedAssignment, $to, $now);
            $lockedAssignment->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.assignment.transitioned',
                actor: $actor,
                targetType: 'kca_assignment',
                targetId: $lockedAssignment->public_id,
                scopeType: 'kca_enrollment',
                scopeId: $lockedAssignment->enrollment()->value('public_id'),
                metadata: ['from' => $from->value, 'to' => $to->value],
            ));

            return $lockedAssignment;
        }, attempts: 3);
    }

    private function recordTransitionTimestamp(
        KcaAssignment $assignment,
        KcaAssignmentState $to,
        CarbonInterface $now,
    ): void {
        match ($to) {
            KcaAssignmentState::Assigned => $assignment->assigned_at = $now,
            KcaAssignmentState::Submitted => $assignment->submitted_at = $now,
            KcaAssignmentState::MentorReview => $assignment->mentor_reviewed_at = $now,
            KcaAssignmentState::AdminReview => $assignment->admin_reviewed_at = $now,
            KcaAssignmentState::FinalAssessment => $assignment->final_assessed_at = $now,
            default => null,
        };
    }
}
