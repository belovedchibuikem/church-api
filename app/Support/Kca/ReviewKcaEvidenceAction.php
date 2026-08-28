<?php

namespace App\Support\Kca;

use App\Exceptions\KcaEvidenceOwnershipException;
use App\Exceptions\KcaEvidenceUnavailableException;
use App\Exceptions\KcaIdempotencyConflictException;
use App\Exceptions\KcaMentorAssignmentException;
use App\Files\FileAssetStatus;
use App\Kca\KcaAssignmentState;
use App\Models\FileAsset;
use App\Models\KcaAssignment;
use App\Models\KcaEvidenceReview;
use App\Models\KcaEvidenceSubmission;
use App\Models\KcaMentorAssignment;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReviewKcaEvidenceAction
{
    public function __construct(
        private KcaAssignmentTransitionService $transitions,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        KcaEvidenceSubmission $evidence,
        Person $reviewer,
        KcaAssignmentState $outcome,
        User $actor,
    ): KcaEvidenceReview {
        if (! in_array($outcome, [
            KcaAssignmentState::Resubmit,
            KcaAssignmentState::Approved,
            KcaAssignmentState::NeedsAttention,
        ], true)) {
            throw new InvalidArgumentException('Mentor evidence reviews require a mentor review outcome.');
        }

        return DB::transaction(function () use ($evidence, $reviewer, $outcome, $actor): KcaEvidenceReview {
            $lockedEvidence = KcaEvidenceSubmission::query()->lockForUpdate()->findOrFail($evidence->getKey());
            $lockedAssignment = KcaAssignment::query()
                ->lockForUpdate()
                ->findOrFail($lockedEvidence->kca_assignment_id);
            $lockedFile = FileAsset::query()->lockForUpdate()->findOrFail($lockedEvidence->file_asset_id);

            if (
                $lockedAssignment->kca_enrollment_id !== $lockedEvidence->kca_enrollment_id
                || $actor->person_id !== $reviewer->getKey()
            ) {
                throw new KcaEvidenceOwnershipException;
            }

            if ($lockedFile->status !== FileAssetStatus::Available || $lockedFile->deleted_at !== null) {
                throw new KcaEvidenceUnavailableException;
            }

            $now = now()->utc();
            $assignedMentor = KcaMentorAssignment::query()
                ->where('kca_enrollment_id', $lockedEvidence->kca_enrollment_id)
                ->where('mentor_person_id', $reviewer->getKey())
                ->where('starts_at', '<=', $now)
                ->where(function (Builder $query) use ($now): void {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                })
                ->lockForUpdate()
                ->first();

            if ($assignedMentor === null) {
                throw new KcaMentorAssignmentException;
            }

            $existing = KcaEvidenceReview::query()
                ->whereBelongsTo($lockedEvidence, 'evidenceSubmission')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (
                    $existing->reviewer_person_id !== $reviewer->getKey()
                    || $existing->outcome !== $outcome
                ) {
                    throw new KcaIdempotencyConflictException;
                }

                return $existing;
            }

            $from = $lockedAssignment->state;
            $this->transitions->assertCanTransition($from, $outcome);
            $review = (new KcaEvidenceReview)->forceFill([
                'kca_evidence_submission_id' => $lockedEvidence->getKey(),
                'reviewer_person_id' => $reviewer->getKey(),
                'reviewed_by_user_id' => $actor->getKey(),
                'outcome' => $outcome,
                'reviewed_at' => $now,
            ]);
            $review->save();

            $lockedAssignment->state = $outcome;
            $lockedAssignment->mentor_reviewed_at = $now;
            $lockedAssignment->last_transitioned_by_user_id = $actor->getKey();
            $lockedAssignment->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.evidence.reviewed',
                actor: $actor,
                targetType: 'kca_evidence_review',
                targetId: $review->public_id,
                scopeType: 'kca_enrollment',
                scopeId: $lockedAssignment->enrollment()->value('public_id'),
                metadata: [
                    'evidence_id' => $lockedEvidence->public_id,
                    'assignment_id' => $lockedAssignment->public_id,
                    'from' => $from->value,
                    'to' => $outcome->value,
                ],
            ));

            return $review;
        }, attempts: 3);
    }
}
