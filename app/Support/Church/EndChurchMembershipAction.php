<?php

namespace App\Support\Church;

use App\Church\ChurchMembershipStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\HomeChurch;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class EndChurchMembershipAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        ChurchMembership $membership,
        string $reasonCode,
        User $actor,
    ): ChurchMembership {
        $reason = new StableReasonCode($reasonCode);

        return DB::transaction(function () use ($membership, $reason, $actor): ChurchMembership {
            $lockedMembership = ChurchMembership::query()
                ->lockForUpdate()
                ->findOrFail($membership->getKey());
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());

            if ($lockedMembership->status === ChurchMembershipStatus::Ended) {
                return $lockedMembership;
            }

            $church = Church::query()->lockForUpdate()->findOrFail($lockedMembership->church_id);
            $homeChurch = $lockedMembership->home_church_id === null
                ? null
                : HomeChurch::query()->lockForUpdate()->findOrFail($lockedMembership->home_church_id);
            $lockedMembership->status = ChurchMembershipStatus::Ended;
            $lockedMembership->active_marker = null;
            $lockedMembership->ended_at = now()->utc();
            $lockedMembership->end_reason_code = $reason->value;
            $lockedMembership->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'church.membership.ended',
                actor: $lockedActor,
                targetType: 'church_membership',
                targetId: $lockedMembership->public_id,
                scopeType: $homeChurch === null ? 'church' : 'home_church',
                scopeId: $homeChurch?->public_id ?? $church->public_id,
                metadata: ['reason_code' => $reason->value],
            ));

            return $lockedMembership;
        }, attempts: 3);
    }
}
