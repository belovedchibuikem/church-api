<?php

namespace App\Mission\Actions;

use App\Models\Church;
use App\Models\MissionSoulJourney;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConnectSoulChurchAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(MissionSoulJourney $journey, Church $church, User $actor): MissionSoulJourney
    {
        return DB::transaction(function () use ($journey, $church, $actor): MissionSoulJourney {
            $locked = MissionSoulJourney::query()->lockForUpdate()->findOrFail($journey->getKey());
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->getKey());

            if ($locked->connected_church_id !== null && (int) $locked->connected_church_id !== (int) $lockedChurch->getKey()) {
                throw new InvalidArgumentException('This soul is already connected to a church. Reassignment requires a follow-up reassignment workflow.');
            }

            $locked->connected_church_id = $lockedChurch->getKey();
            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'mission.soul.church_connected',
                actor: $actor,
                targetType: 'mission_soul_journey',
                targetId: $locked->public_id,
                scopeType: 'crusade',
                scopeId: $locked->crusade?->public_id,
                metadata: ['church_id' => $lockedChurch->public_id],
            ));

            return $locked->fresh(['connectedChurch:id,public_id,name', 'crusade:id,public_id,name']) ?? $locked;
        }, attempts: 3);
    }
}
