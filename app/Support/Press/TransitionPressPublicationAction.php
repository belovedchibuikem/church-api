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

            if ($to === PressPublicationStatus::PublicationApproval
                && $from === PressPublicationStatus::Design
                && $lockedPublication->requiresIsbnToPublish()
                && $lockedPublication->isbn === null) {
                throw new DomainException('Books must receive an ISBN before publication approval.');
            }

            if ($to === PressPublicationStatus::Published) {
                if ($lockedPublication->requiresIsbnToPublish() && $lockedPublication->isbn === null) {
                    throw new DomainException('A publication must have a valid ISBN before publication.');
                }

                if ($lockedPublication->hasUnreadyRequiredAssets()) {
                    throw new DomainException('Required digital assets must be ready before publication.');
                }
            }

            $now = now()->utc();
            $lockedPublication->status = $to;
            $lockedPublication->status_changed_at = $now;

            if ($to === PressPublicationStatus::Published) {
                $lockedPublication->published_at = $now;
                $lockedPublication->scheduled_publish_at = null;
            }

            if ($to === PressPublicationStatus::Distribution) {
                $lockedPublication->distributed_at = $now;
                $lockedPublication->availability = PressPublicationAvailability::Available;
            }

            if ($to === PressPublicationStatus::Unpublished) {
                $lockedPublication->unpublished_at = $now;
                $lockedPublication->availability = PressPublicationAvailability::Unavailable;
                $lockedPublication->scheduled_unpublish_at = null;
            }

            if ($to === PressPublicationStatus::Archived) {
                $lockedPublication->archived_at = $now;
                $lockedPublication->availability = PressPublicationAvailability::Unavailable;
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
