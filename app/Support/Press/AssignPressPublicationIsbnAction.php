<?php

namespace App\Support\Press;

use App\Models\PressPublication;
use App\Models\User;
use App\Press\Isbn;
use App\Press\PressPublicationStatus;
use App\Press\PressWorkflowReason;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Support\Facades\DB;

class AssignPressPublicationIsbnAction
{
    public function __construct(
        private RecordPressPublicationTransitionAction $recordTransition,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        PressPublication $publication,
        string $isbnValue,
        User $actor,
        string $reasonCode,
    ): PressPublication {
        $isbn = Isbn::from($isbnValue);
        $reasonCode = PressWorkflowReason::validate($reasonCode);

        return DB::transaction(function () use ($publication, $isbn, $actor, $reasonCode): PressPublication {
            $lockedPublication = PressPublication::query()->lockForUpdate()->findOrFail($publication->getKey());

            if ($lockedPublication->status === PressPublicationStatus::IsbnAssignment) {
                if ($lockedPublication->isbn === $isbn->value) {
                    return $lockedPublication;
                }

                throw new DomainException('The publication already has a different ISBN.');
            }

            if ($lockedPublication->status !== PressPublicationStatus::Design) {
                throw new DomainException('ISBN assignment is only allowed after design.');
            }

            $from = $lockedPublication->status;
            $to = PressPublicationStatus::IsbnAssignment;
            $lockedPublication->isbn = $isbn->value;
            $lockedPublication->isbn_type = $isbn->type;
            $lockedPublication->status = $to;
            $lockedPublication->status_changed_at = now()->utc();
            $lockedPublication->save();

            $this->recordTransition->handle($lockedPublication, $actor, $from, $to, $reasonCode);
            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'press.publication.isbn_assigned',
                actor: $actor,
                targetType: 'press_publication',
                targetId: $lockedPublication->public_id,
                scopeType: 'press_publication',
                scopeId: $lockedPublication->public_id,
                metadata: ['isbn_type' => $isbn->type->value, 'to' => $to->value, 'reason_code' => $reasonCode],
            ));

            return $lockedPublication;
        }, attempts: 3);
    }
}
