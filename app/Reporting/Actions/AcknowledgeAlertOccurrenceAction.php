<?php

namespace App\Reporting\Actions;

use App\Exceptions\AlertExecutionDeniedException;
use App\Exceptions\AlertInvalidStateException;
use App\Models\AlertOccurrence;
use App\Models\User;
use App\Reporting\AlertOccurrenceStatus;
use App\Reporting\Contracts\AlertVisibilityPolicy;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class AcknowledgeAlertOccurrenceAction
{
    public function __construct(
        private AlertVisibilityPolicy $visibilityPolicy,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(AlertOccurrence $occurrence, User $actor): AlertOccurrence
    {
        return DB::transaction(function () use ($occurrence, $actor): AlertOccurrence {
            $locked = AlertOccurrence::query()->lockForUpdate()->findOrFail($occurrence->getKey());

            if (! $this->visibilityPolicy->allows($actor, $locked)) {
                throw new AlertExecutionDeniedException('alert_visibility_denied');
            }

            if ($locked->status === AlertOccurrenceStatus::Acknowledged) {
                return $locked;
            }

            if ($locked->status !== AlertOccurrenceStatus::Open) {
                throw new AlertInvalidStateException('Only open alerts may be acknowledged.');
            }

            $locked->forceFill([
                'status' => AlertOccurrenceStatus::Acknowledged,
                'acknowledged_by_user_id' => $actor->getKey(),
                'acknowledged_at' => now()->utc(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'reporting.alert_occurrence.acknowledged',
                actor: $actor,
                targetType: 'alert_occurrence',
                targetId: $locked->public_id,
            ));

            return $locked;
        }, attempts: 3);
    }
}
