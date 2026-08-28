<?php

namespace App\Support\Church;

use App\Church\ChurchMembershipStatus;
use App\Church\HomeChurchStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\HomeChurch;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StartChurchMembershipAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        Person $person,
        Church $church,
        ?HomeChurch $homeChurch = null,
        ?CarbonInterface $joinedAt = null,
        ?User $actor = null,
    ): ChurchMembership {
        return DB::transaction(function () use (
            $person,
            $church,
            $homeChurch,
            $joinedAt,
            $actor,
        ): ChurchMembership {
            $lockedPerson = Person::query()->lockForUpdate()->findOrFail($person->getKey());
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->getKey());
            $lockedHomeChurch = $homeChurch === null
                ? null
                : HomeChurch::query()->lockForUpdate()->findOrFail($homeChurch->getKey());
            $this->assertHomeChurchMembershipIsValid($lockedChurch, $lockedHomeChurch);

            $duplicateExists = ChurchMembership::query()
                ->whereBelongsTo($lockedPerson)
                ->whereBelongsTo($lockedChurch)
                ->where('active_marker', 1)
                ->lockForUpdate()
                ->exists();

            if ($duplicateExists) {
                throw new InvalidArgumentException('The person already has an active membership at this church.');
            }

            $membership = new ChurchMembership([
                'person_id' => $lockedPerson->getKey(),
                'church_id' => $lockedChurch->getKey(),
                'home_church_id' => $lockedHomeChurch?->getKey(),
                'joined_at' => ($joinedAt ?? now())->utc(),
            ]);
            $membership->status = ChurchMembershipStatus::Active;
            $membership->active_marker = 1;
            $membership->ended_at = null;
            $membership->end_reason_code = null;
            $membership->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'church.membership.started',
                actor: $actor,
                targetType: 'church_membership',
                targetId: $membership->public_id,
                scopeType: $lockedHomeChurch === null ? 'church' : 'home_church',
                scopeId: $lockedHomeChurch?->public_id ?? $lockedChurch->public_id,
                metadata: ['person_id' => $lockedPerson->public_id],
            ));

            return $membership;
        }, attempts: 3);
    }

    private function assertHomeChurchMembershipIsValid(
        Church $church,
        ?HomeChurch $homeChurch,
    ): void {
        if ($homeChurch === null) {
            return;
        }

        if (
            $homeChurch->church_id !== $church->getKey()
            || $homeChurch->status !== HomeChurchStatus::Active
        ) {
            throw new InvalidArgumentException('Membership requires an active Home Church belonging to the church.');
        }
    }
}
