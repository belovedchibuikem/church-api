<?php

namespace App\Support\Press;

use App\Models\PressPublication;
use App\Models\User;
use App\Press\PressPublicationAvailability;
use App\Press\PressPublicationStatus;
use App\Press\PressWorkflowReason;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Support\Facades\DB;

class TransitionPressPublicationAction
{
    public function __construct(
        private RecordPressPublicationTransitionAction $recordTransition,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        PressPublication $publication,
        PressPublicationStatus $to,
        User $actor,
        string $reasonCode,
    ): PressPublication {
        $reasonCode = PressWorkflowReason::validate($reasonCode);

        return DB::transaction(function () use ($publication, $to, $actor, $reasonCode): PressPublication {
            $lockedPublication = PressPublication::query()->lockForUpdate()->findOrFail($publication->getKey());
            $from = $lockedPublication->status;

            if ($from === $to) {
                return $lockedPublication;
            }

            if ($to === PressPublicationStatus::IsbnAssignment) {
                throw new DomainException('Use the ISBN assignment action to enter the ISBN workflow state.');
            }

            if (! $from->canTransitionTo($to)) {
                throw new DomainException("Publication cannot transition from {$from->value} to {$to->value}.");
            }

            if ($to === PressPublicationStatus::Published && $lockedPublication->isbn === null) {
                throw new DomainException('A publication must have a valid ISBN before publication.');
            }

            $now = now()->utc();
            $lockedPublication->status = $to;
            $lockedPublication->status_changed_at = $now;

            if ($to === PressPublicationStatus::Published) {
                $lockedPublication->published_at = $now;
            }

            if ($to === PressPublicationStatus::Distribution) {
                $lockedPublication->distributed_at = $now;
                $lockedPublication->availability = PressPublicationAvailability::Available;
            }

            $lockedPublication->save();
            $this->recordTransition->handle($lockedPublication, $actor, $from, $to, $reasonCode);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'press.publication.transitioned',
                actor: $actor,
                targetType: 'press_publication',
                targetId: $lockedPublication->public_id,
                scopeType: 'press_publication',
                scopeId: $lockedPublication->public_id,
                metadata: ['from' => $from->value, 'to' => $to->value, 'reason_code' => $reasonCode],
            ));

            return $lockedPublication;
        }, attempts: 3);
    }
}
