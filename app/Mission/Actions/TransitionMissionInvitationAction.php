<?php

namespace App\Mission\Actions;

use App\Exceptions\MissionInvalidTransitionException;
use App\Mission\MissionInvitationStatus;
use App\Models\MissionInvitation;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransitionMissionInvitationAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        MissionInvitation $invitation,
        MissionInvitationStatus $targetStatus,
        ?string $reasonCode = null,
        ?User $actor = null,
    ): MissionInvitation {
        $this->assertSafeCode($reasonCode);

        return DB::transaction(function () use ($invitation, $targetStatus, $reasonCode, $actor): MissionInvitation {
            $lockedInvitation = MissionInvitation::query()->lockForUpdate()->findOrFail($invitation->getKey());
            $sourceStatus = $lockedInvitation->status;

            if ($sourceStatus === $targetStatus) {
                return $lockedInvitation;
            }

            if ($sourceStatus->next() !== $targetStatus) {
                throw new MissionInvalidTransitionException(
                    "Mission invitation cannot transition from {$sourceStatus->value} to {$targetStatus->value}.",
                );
            }

            $lockedInvitation->forceFill([
                'status' => $targetStatus,
                'transition_reason_code' => $reasonCode,
                'status_changed_at' => now()->utc(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'mission.invitation.transitioned',
                actor: $actor,
                targetType: 'mission_invitation',
                targetId: $lockedInvitation->public_id,
                scopeType: $lockedInvitation->crusade_id === null ? null : 'crusade',
                scopeId: $lockedInvitation->crusade?->public_id,
                metadata: array_filter([
                    'from_status' => $sourceStatus->value,
                    'to_status' => $targetStatus->value,
                    'reason_code' => $reasonCode,
                ], static fn (mixed $value): bool => $value !== null),
            ));

            return $lockedInvitation;
        }, attempts: 3);
    }

    private function assertSafeCode(?string $code): void
    {
        if ($code !== null && (Str::length($code) > 100 || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $code))) {
            throw new InvalidArgumentException('Mission reason codes must be stable lowercase identifiers.');
        }
    }
}
