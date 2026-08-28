<?php

namespace App\Support\Kca;

use App\Exceptions\KcaEvidenceOwnershipException;
use App\Exceptions\KcaEvidenceUnavailableException;
use App\Exceptions\KcaIdempotencyConflictException;
use App\Files\FileAssetClassification;
use App\Files\FileAssetStatus;
use App\Kca\KcaAssignmentState;
use App\Models\FileAsset;
use App\Models\KcaAssignment;
use App\Models\KcaEnrollment;
use App\Models\KcaEvidenceSubmission;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SubmitKcaEvidenceAction
{
    public function __construct(
        private KcaAssignmentTransitionService $transitions,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        KcaAssignment $assignment,
        KcaEnrollment $enrollment,
        FileAsset $fileAsset,
        Person $submittedBy,
        string $idempotencyKey,
        User $actor,
    ): KcaEvidenceSubmission {
        if ($idempotencyKey === '' || Str::length($idempotencyKey) > 255) {
            throw new InvalidArgumentException('Evidence idempotency keys must contain 1 to 255 characters.');
        }

        $idempotencyKeyHash = hash_hmac('sha256', $idempotencyKey, $this->hashKey());

        return DB::transaction(function () use (
            $assignment,
            $enrollment,
            $fileAsset,
            $submittedBy,
            $idempotencyKeyHash,
            $actor,
        ): KcaEvidenceSubmission {
            $lockedAssignment = KcaAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            $lockedEnrollment = KcaEnrollment::query()->lockForUpdate()->findOrFail($enrollment->getKey());
            $lockedFileAsset = FileAsset::query()->lockForUpdate()->findOrFail($fileAsset->getKey());

            $existing = KcaEvidenceSubmission::query()
                ->where('kca_assignment_id', $lockedAssignment->getKey())
                ->where('idempotency_key_hash', $idempotencyKeyHash)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (
                    $existing->kca_enrollment_id !== $lockedEnrollment->getKey()
                    || $existing->file_asset_id !== $lockedFileAsset->getKey()
                    || $existing->submitted_by_person_id !== $submittedBy->getKey()
                ) {
                    throw new KcaIdempotencyConflictException;
                }

                return $existing;
            }

            if (
                $lockedAssignment->kca_enrollment_id !== $lockedEnrollment->getKey()
                || $lockedEnrollment->person_id !== $submittedBy->getKey()
                || $lockedFileAsset->owner_person_id !== $submittedBy->getKey()
                || $actor->person_id !== $submittedBy->getKey()
            ) {
                throw new KcaEvidenceOwnershipException;
            }

            if (
                $lockedFileAsset->purpose !== 'kca.evidence'
                || ! in_array($lockedFileAsset->classification, [
                    FileAssetClassification::Confidential,
                    FileAssetClassification::Restricted,
                ], true)
                || $lockedFileAsset->status === FileAssetStatus::Rejected
                || $lockedFileAsset->deleted_at !== null
            ) {
                throw new KcaEvidenceUnavailableException;
            }

            $from = $lockedAssignment->state;
            $this->transitions->assertCanTransition($from, KcaAssignmentState::Submitted);
            $now = now()->utc();
            $submission = (new KcaEvidenceSubmission)->forceFill([
                'kca_assignment_id' => $lockedAssignment->getKey(),
                'kca_enrollment_id' => $lockedEnrollment->getKey(),
                'file_asset_id' => $lockedFileAsset->getKey(),
                'submitted_by_person_id' => $submittedBy->getKey(),
                'idempotency_key_hash' => $idempotencyKeyHash,
                'submitted_at' => $now,
            ]);
            $submission->save();

            $lockedAssignment->state = KcaAssignmentState::Submitted;
            $lockedAssignment->submitted_at = $now;
            $lockedAssignment->last_transitioned_by_user_id = $actor->getKey();
            $lockedAssignment->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.evidence.submitted',
                actor: $actor,
                targetType: 'kca_evidence_submission',
                targetId: $submission->public_id,
                scopeType: 'kca_enrollment',
                scopeId: $lockedEnrollment->public_id,
                metadata: [
                    'assignment_id' => $lockedAssignment->public_id,
                    'file_asset_id' => $lockedFileAsset->public_id,
                    'from' => $from->value,
                    'to' => KcaAssignmentState::Submitted->value,
                ],
            ));

            return $submission;
        }, attempts: 3);
    }

    private function hashKey(): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new InvalidArgumentException('The application key is required for evidence idempotency.');
        }

        return $key;
    }
}
