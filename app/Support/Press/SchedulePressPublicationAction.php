<?php

namespace App\Support\Press;

use App\Models\PressPublication;
use App\Models\User;
use App\Press\PressPublicationStatus;
use App\Press\PressWorkflowReason;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Support\Facades\DB;

class SchedulePressPublicationAction
{
    public function __construct(
        private TransitionPressPublicationAction $transition,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        PressPublication $publication,
        User $actor,
        ?string $publishAt,
        ?string $unpublishAt,
        string $reasonCode,
    ): PressPublication {
        $reasonCode = PressWorkflowReason::validate($reasonCode);

        return DB::transaction(function () use ($publication, $actor, $publishAt, $unpublishAt, $reasonCode): PressPublication {
            $locked = PressPublication::query()->lockForUpdate()->findOrFail($publication->getKey());

            if ($publishAt !== null) {
                $at = new \DateTimeImmutable($publishAt);
                $locked->scheduled_publish_at = $at;
                $locked->scheduled_by_user_id = $actor->getKey();
                $locked->save();

                if ($locked->status === PressPublicationStatus::PublicationApproval) {
                    $locked = $this->transition->handle($locked, PressPublicationStatus::Scheduled, $actor, $reasonCode);
                } elseif ($locked->status !== PressPublicationStatus::Scheduled) {
                    throw new DomainException('Scheduling publish is only allowed from publication approval or scheduled state.');
                }
            }

            if ($unpublishAt !== null) {
                $locked->scheduled_unpublish_at = new \DateTimeImmutable($unpublishAt);
                $locked->scheduled_by_user_id = $actor->getKey();
                $locked->save();
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'press.publication.scheduled',
                actor: $actor,
                targetType: 'press_publication',
                targetId: $locked->public_id,
                scopeType: 'press_publication',
                scopeId: $locked->public_id,
                metadata: [
                    'scheduled_publish_at' => $locked->scheduled_publish_at?->toIso8601String(),
                    'scheduled_unpublish_at' => $locked->scheduled_unpublish_at?->toIso8601String(),
                    'reason_code' => $reasonCode,
                ],
            ));

            return $locked->fresh() ?? $locked;
        }, attempts: 3);
    }
}
