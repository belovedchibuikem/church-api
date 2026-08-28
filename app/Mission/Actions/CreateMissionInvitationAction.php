<?php

namespace App\Mission\Actions;

use App\Models\Crusade;
use App\Models\Location;
use App\Models\MissionInvitation;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class CreateMissionInvitationAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(Crusade $crusade, Person $requester, Location $location, User $actor): MissionInvitation
    {
        return DB::transaction(function () use ($crusade, $requester, $location, $actor): MissionInvitation {
            $lockedCrusade = Crusade::query()->lockForUpdate()->findOrFail($crusade->getKey());
            $lockedRequester = Person::query()->lockForUpdate()->findOrFail($requester->getKey());
            $lockedLocation = Location::query()->lockForUpdate()->findOrFail($location->getKey());

            $invitation = (new MissionInvitation)->forceFill([
                'crusade_id' => $lockedCrusade->getKey(),
                'requester_person_id' => $lockedRequester->getKey(),
                'requested_location_id' => $lockedLocation->getKey(),
                'status_changed_at' => now()->utc(),
            ]);
            $invitation->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'mission.invitation.created',
                actor: $actor,
                targetType: 'mission_invitation',
                targetId: $invitation->public_id,
                scopeType: 'crusade',
                scopeId: $lockedCrusade->public_id,
                metadata: [
                    'requester_person_id' => $lockedRequester->public_id,
                    'requested_location_id' => $lockedLocation->public_id,
                ],
            ));

            return $invitation;
        }, attempts: 3);
    }
}
