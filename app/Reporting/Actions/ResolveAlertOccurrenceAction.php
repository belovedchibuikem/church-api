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
use Illuminate\Support\Str;
use InvalidArgumentException;

class ResolveAlertOccurrenceAction
{
    public function __construct(
        private AlertVisibilityPolicy $visibilityPolicy,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        AlertOccurrence $occurrence,
        string $reasonCode,
        User $actor,
    ): AlertOccurrence {
        if (
            Str::length($reasonCode) > 100
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $reasonCode)
        ) {
            throw new InvalidArgumentException('Alert resolution reasons must be stable lowercase identifiers.');
        }

        return DB::transaction(function () use ($occurrence, $reasonCode, $actor): AlertOccurrence {
            $locked = AlertOccurrence::query()->lockForUpdate()->findOrFail($occurrence->getKey());

            if (! $this->visibilityPolicy->allows($actor, $locked)) {
                throw new AlertExecutionDeniedException('alert_visibility_denied');
            }

            if ($locked->status === AlertOccurrenceStatus::Resolved) {
                if ($locked->resolution_reason_code !== $reasonCode) {
                    throw new AlertInvalidStateException('Resolved alerts cannot be changed.');
                }

                return $locked;
            }

            $locked->forceFill([
                'status' => AlertOccurrenceStatus::Resolved,
                'active_marker' => null,
                'resolved_by_user_id' => $actor->getKey(),
                'resolved_at' => now()->utc(),
                'resolution_reason_code' => $reasonCode,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'reporting.alert_occurrence.resolved',
                actor: $actor,
                targetType: 'alert_occurrence',
                targetId: $locked->public_id,
                metadata: ['reason_code' => $reasonCode],
            ));

            return $locked;
        }, attempts: 3);
    }
}
