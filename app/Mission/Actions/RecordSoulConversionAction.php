<?php

namespace App\Mission\Actions;

use App\Models\MissionSoulJourney;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordSoulConversionAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(MissionSoulJourney $journey, string $reasonCode, User $actor): MissionSoulJourney
    {
        if (Str::length($reasonCode) > 100 || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $reasonCode)) {
            throw new InvalidArgumentException('Conversion reason codes must be stable lowercase identifiers.');
        }

        return DB::transaction(function () use ($journey, $reasonCode, $actor): MissionSoulJourney {
            $locked = MissionSoulJourney::query()->lockForUpdate()->findOrFail($journey->getKey());
            if ($locked->converted_at !== null) {
                return $locked;
            }

            $locked->forceFill([
                'converted_at' => now()->utc(),
                'conversion_reason_code' => $reasonCode,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'mission.soul.converted',
                actor: $actor,
                targetType: 'mission_soul_journey',
                targetId: $locked->public_id,
                scopeType: 'crusade',
                scopeId: $locked->crusade?->public_id,
                metadata: ['reason_code' => $reasonCode],
            ));

            return $locked;
        }, attempts: 3);
    }
}
