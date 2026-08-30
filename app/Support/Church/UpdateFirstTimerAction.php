<?php

namespace App\Support\Church;

use App\Church\HomeChurchStatus;
use App\Models\Church;
use App\Models\FirstTimer;
use App\Models\HomeChurch;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateFirstTimerAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        FirstTimer $firstTimer,
        Person $person,
        Church $church,
        ?HomeChurch $homeChurch = null,
        ?CarbonInterface $registeredAt = null,
        ?User $actor = null,
    ): FirstTimer {
        return DB::transaction(function () use (
            $firstTimer,
            $person,
            $church,
            $homeChurch,
            $registeredAt,
            $actor,
        ): FirstTimer {
            $locked = FirstTimer::query()->lockForUpdate()->findOrFail($firstTimer->getKey());
            $lockedPerson = Person::query()->lockForUpdate()->findOrFail($person->getKey());
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->getKey());
            $lockedHomeChurch = $homeChurch === null
                ? null
                : HomeChurch::query()->lockForUpdate()->findOrFail($homeChurch->getKey());

            if (
                $lockedHomeChurch !== null
                && (
                    $lockedHomeChurch->church_id !== $lockedChurch->getKey()
                    || $lockedHomeChurch->status !== HomeChurchStatus::Active
                )
            ) {
                throw new InvalidArgumentException('First timers require an active Home Church belonging to the church.');
            }

            $duplicateExists = FirstTimer::query()
                ->whereBelongsTo($lockedPerson)
                ->whereBelongsTo($lockedChurch)
                ->whereKeyNot($locked->getKey())
                ->lockForUpdate()
                ->exists();

            if ($duplicateExists) {
                throw new InvalidArgumentException('The person is already registered as a first timer at this church.');
            }

            $locked->person_id = $lockedPerson->getKey();
            $locked->church_id = $lockedChurch->getKey();
            $locked->home_church_id = $lockedHomeChurch?->getKey();
            if ($registeredAt !== null) {
                $locked->registered_at = $registeredAt->toImmutable()->utc();
            }
            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'church.first_timer.updated',
                actor: $actor,
                targetType: 'first_timer',
                targetId: $locked->public_id,
                scopeType: $lockedHomeChurch === null ? 'church' : 'home_church',
                scopeId: $lockedHomeChurch?->public_id ?? $lockedChurch->public_id,
                metadata: [
                    'person_id' => $lockedPerson->public_id,
                    'church_id' => $lockedChurch->public_id,
                ],
            ));

            return $locked;
        }, attempts: 3);
    }
}
