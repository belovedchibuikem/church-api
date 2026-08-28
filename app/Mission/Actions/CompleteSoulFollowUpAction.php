<?php

namespace App\Mission\Actions;

use App\Exceptions\MissionJourneyStateException;
use App\Mission\MissionSoulJourneyStatus;
use App\Models\MissionSoulJourney;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CompleteSoulFollowUpAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        MissionSoulJourney $soulJourney,
        string $reasonCode,
        ?User $actor = null,
    ): MissionSoulJourney {
        $this->assertSafeCode($reasonCode);

        return DB::transaction(function () use ($soulJourney, $reasonCode, $actor): MissionSoulJourney {
            $lockedJourney = MissionSoulJourney::query()->lockForUpdate()->findOrFail($soulJourney->getKey());

            if ($lockedJourney->status === MissionSoulJourneyStatus::FollowUpCompleted) {
                if ($lockedJourney->follow_up_completion_reason_code !== $reasonCode) {
                    throw new MissionJourneyStateException('Completed follow-up cannot be rewritten with a different reason code.');
                }

                return $lockedJourney;
            }

            if ($lockedJourney->status !== MissionSoulJourneyStatus::FollowUpActive || $lockedJourney->closed_at !== null) {
                throw new MissionJourneyStateException('Follow-up may only be completed after at least one recorded interaction.');
            }

            $lockedJourney->forceFill([
                'status' => MissionSoulJourneyStatus::FollowUpCompleted,
                'follow_up_completed_at' => now()->utc(),
                'follow_up_completion_reason_code' => $reasonCode,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'mission.soul.follow_up_completed',
                actor: $actor,
                targetType: 'mission_soul_journey',
                targetId: $lockedJourney->public_id,
                scopeType: 'crusade',
                scopeId: $lockedJourney->crusade->public_id,
                metadata: ['reason_code' => $reasonCode],
            ));

            return $lockedJourney;
        }, attempts: 3);
    }

    private function assertSafeCode(string $code): void
    {
        if (Str::length($code) > 100 || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $code)) {
            throw new InvalidArgumentException('Mission completion reason codes must be stable lowercase identifiers.');
        }
    }
}
