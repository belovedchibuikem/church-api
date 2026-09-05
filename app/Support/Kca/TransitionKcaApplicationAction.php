<?php

namespace App\Support\Kca;

use App\Kca\KcaApplicationState;
use App\Models\KcaAdmissionDecision;
use App\Models\KcaApplication;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransitionKcaApplicationAction
{
    public function __construct(
        private KcaApplicationTransitionService $transitions,
        private RecordAuditEventAction $recordAuditEvent,
        private IssueKcaAdmissionLetterAction $issueAdmissionLetter,
    ) {}

    public function handle(
        KcaApplication $application,
        KcaApplicationState $to,
        User $actor,
        ?string $reasonCode = null,
    ): KcaApplication {
        $this->validateReasonCode($reasonCode);

        return DB::transaction(function () use ($application, $to, $actor, $reasonCode): KcaApplication {
            $lockedApplication = KcaApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->getKey());
            $from = $lockedApplication->status;

            $this->transitions->assertCanTransition($from, $to);
            if ($to->requiresDecisionReason() && ($reasonCode === null || $reasonCode === '')) {
                throw new InvalidArgumentException('Adverse KCA decisions require a reason_code.');
            }

            $now = now()->utc();
            $lockedApplication->status = $to;

            if ($to === KcaApplicationState::Reviewed) {
                $lockedApplication->reviewed_at = $now;
            }

            $lockedApplication->save();

            if ($to->isAdmissionOutcome()) {
                (new KcaAdmissionDecision)->forceFill([
                    'kca_application_id' => $lockedApplication->getKey(),
                    'outcome' => $to,
                    'reason_code' => $reasonCode,
                    'decided_by_user_id' => $actor->getKey(),
                    'decided_at' => $now,
                ])->save();
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: $to->isAdmissionOutcome()
                    ? 'kca.application.admission_decided'
                    : 'kca.application.reviewed',
                actor: $actor,
                targetType: 'kca_application',
                targetId: $lockedApplication->public_id,
                metadata: array_filter([
                    'from' => $from->value,
                    'to' => $to->value,
                    'reason_code' => $reasonCode,
                ], static fn (mixed $value): bool => $value !== null),
            ));

            if ($to->permitsEnrollment()) {
                $this->issueAdmissionLetter->handle($lockedApplication->fresh(), $actor);
            }

            return $lockedApplication;
        }, attempts: 3);
    }

    private function validateReasonCode(?string $reasonCode): void
    {
        if ($reasonCode === null) {
            return;
        }

        if (
            Str::length($reasonCode) > 100
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $reasonCode)
        ) {
            throw new InvalidArgumentException('Admission reason codes must be stable lowercase identifiers.');
        }
    }
}
