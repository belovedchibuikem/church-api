<?php

namespace App\Support\Church;

use App\Church\Contracts\FirstTimerFollowUpDuePolicy;
use App\Church\FollowUpTaskStatus;
use App\Church\FollowUpTaskType;
use App\Church\HomeChurchStatus;
use App\Models\Church;
use App\Models\FirstTimer;
use App\Models\FollowUpTask;
use App\Models\HomeChurch;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegisterFirstTimerAction
{
    public function __construct(
        private FirstTimerFollowUpDuePolicy $followUpDuePolicy,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        Person $person,
        Church $church,
        ?HomeChurch $homeChurch = null,
        ?Person $assignedFollowUpPerson = null,
        ?CarbonInterface $registeredAt = null,
        ?User $actor = null,
    ): FirstTimer {
        return DB::transaction(function () use (
            $person,
            $church,
            $homeChurch,
            $assignedFollowUpPerson,
            $registeredAt,
            $actor,
        ): FirstTimer {
            $lockedPerson = Person::query()->lockForUpdate()->findOrFail($person->getKey());
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->getKey());
            $lockedHomeChurch = $homeChurch === null
                ? null
                : HomeChurch::query()->lockForUpdate()->findOrFail($homeChurch->getKey());
            $assignedPerson = $assignedFollowUpPerson === null
                ? null
                : Person::query()->lockForUpdate()->findOrFail($assignedFollowUpPerson->getKey());
            $this->assertHomeChurchIsValid($lockedChurch, $lockedHomeChurch);

            $duplicateExists = FirstTimer::query()
                ->whereBelongsTo($lockedPerson)
                ->whereBelongsTo($lockedChurch)
                ->lockForUpdate()
                ->exists();

            if ($duplicateExists) {
                throw new InvalidArgumentException('The person is already registered as a first timer at this church.');
            }

            $registered = ($registeredAt ?? now())->toImmutable()->utc();
            $dueAt = $this->followUpDuePolicy->dueAt($lockedChurch, $registered);
            $firstTimer = new FirstTimer([
                'person_id' => $lockedPerson->getKey(),
                'church_id' => $lockedChurch->getKey(),
                'home_church_id' => $lockedHomeChurch?->getKey(),
                'registered_at' => $registered,
            ]);
            $firstTimer->contacted_at = null;
            $firstTimer->save();
            $followUpTask = new FollowUpTask([
                'first_timer_id' => $firstTimer->getKey(),
                'assigned_to_person_id' => $assignedPerson?->getKey(),
                'type' => FollowUpTaskType::FirstTimerContact,
                'due_at' => $dueAt,
            ]);
            $followUpTask->status = FollowUpTaskStatus::Pending;
            $followUpTask->completed_at = null;
            $followUpTask->completion_reason_code = null;
            $followUpTask->save();
            $scopeType = $lockedHomeChurch === null ? 'church' : 'home_church';
            $scopeId = $lockedHomeChurch?->public_id ?? $lockedChurch->public_id;

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'church.first_timer.registered',
                actor: $actor,
                targetType: 'first_timer',
                targetId: $firstTimer->public_id,
                scopeType: $scopeType,
                scopeId: $scopeId,
                metadata: ['person_id' => $lockedPerson->public_id],
            ));
            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'church.follow_up.created',
                actor: $actor,
                targetType: 'follow_up_task',
                targetId: $followUpTask->public_id,
                scopeType: $scopeType,
                scopeId: $scopeId,
                metadata: [
                    'first_timer_id' => $firstTimer->public_id,
                    'type' => FollowUpTaskType::FirstTimerContact->value,
                    'due_at' => $dueAt->toIso8601String(),
                ],
            ));

            return $firstTimer->setRelation('followUpTasks', collect([$followUpTask]));
        }, attempts: 3);
    }

    private function assertHomeChurchIsValid(Church $church, ?HomeChurch $homeChurch): void
    {
        if ($homeChurch === null) {
            return;
        }

        if (
            $homeChurch->church_id !== $church->getKey()
            || $homeChurch->status !== HomeChurchStatus::Active
        ) {
            throw new InvalidArgumentException('First timers require an active Home Church belonging to the church.');
        }
    }
}
