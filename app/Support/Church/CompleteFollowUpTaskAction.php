<?php

namespace App\Support\Church;

use App\Church\FollowUpTaskStatus;
use App\Church\FollowUpTaskType;
use App\Models\Church;
use App\Models\FirstTimer;
use App\Models\FollowUpTask;
use App\Models\HomeChurch;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CompleteFollowUpTaskAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        FollowUpTask $task,
        string $reasonCode,
        User $actor,
    ): FollowUpTask {
        $reason = new StableReasonCode($reasonCode);

        return DB::transaction(function () use ($task, $reason, $actor): FollowUpTask {
            $lockedTask = FollowUpTask::query()->lockForUpdate()->findOrFail($task->getKey());
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());

            if ($lockedTask->status === FollowUpTaskStatus::Completed) {
                return $lockedTask;
            }

            if ($lockedTask->status !== FollowUpTaskStatus::Pending) {
                throw new InvalidArgumentException('Only pending follow-up tasks can be completed.');
            }

            $firstTimer = FirstTimer::query()->lockForUpdate()->findOrFail($lockedTask->first_timer_id);
            $church = Church::query()->lockForUpdate()->findOrFail($firstTimer->church_id);
            $homeChurch = $firstTimer->home_church_id === null
                ? null
                : HomeChurch::query()->lockForUpdate()->findOrFail($firstTimer->home_church_id);
            $completedAt = now()->utc();
            $lockedTask->status = FollowUpTaskStatus::Completed;
            $lockedTask->completed_at = $completedAt;
            $lockedTask->completion_reason_code = $reason->value;
            $lockedTask->save();

            if (
                $lockedTask->type === FollowUpTaskType::FirstTimerContact
                && $firstTimer->contacted_at === null
            ) {
                $firstTimer->contacted_at = $completedAt;
                $firstTimer->save();
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'church.follow_up.completed',
                actor: $lockedActor,
                targetType: 'follow_up_task',
                targetId: $lockedTask->public_id,
                scopeType: $homeChurch === null ? 'church' : 'home_church',
                scopeId: $homeChurch?->public_id ?? $church->public_id,
                metadata: ['reason_code' => $reason->value],
            ));

            return $lockedTask;
        }, attempts: 3);
    }
}
