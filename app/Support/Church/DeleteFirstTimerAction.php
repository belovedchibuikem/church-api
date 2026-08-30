<?php

namespace App\Support\Church;

use App\Models\FirstTimer;
use App\Models\FollowUpTask;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class DeleteFirstTimerAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(FirstTimer $firstTimer, ?User $actor = null): void
    {
        DB::transaction(function () use ($firstTimer, $actor): void {
            $locked = FirstTimer::query()->with('church:id,public_id', 'homeChurch:id,public_id')->lockForUpdate()->findOrFail($firstTimer->getKey());
            $publicId = $locked->public_id;
            $scopeType = $locked->homeChurch === null ? 'church' : 'home_church';
            $scopeId = $locked->homeChurch?->public_id ?? $locked->church?->public_id ?? $publicId;

            FollowUpTask::query()->where('first_timer_id', $locked->getKey())->delete();
            $locked->delete();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'church.first_timer.deleted',
                actor: $actor,
                targetType: 'first_timer',
                targetId: $publicId,
                scopeType: $scopeType,
                scopeId: $scopeId,
            ));
        }, attempts: 3);
    }
}
