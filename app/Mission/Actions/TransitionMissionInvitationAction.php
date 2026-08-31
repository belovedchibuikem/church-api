<?php

namespace App\Mission\Actions;

use App\Exceptions\MissionInvalidTransitionException;
use App\Mission\CrusadeStatus;
use App\Mission\MissionInvitationStatus;
use App\Models\Crusade;
use App\Models\MissionInvitation;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonImmutable;
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

            if (! $sourceStatus->canTransitionTo($targetStatus)) {
                throw new MissionInvalidTransitionException(
                    "Mission invitation cannot transition from {$sourceStatus->value} to {$targetStatus->value}.",
                );
            }

            if ($targetStatus->requiresReason() && ($reasonCode === null || $reasonCode === '')) {
                throw new InvalidArgumentException('A reason_code is required for this invitation decision.');
            }

            $lockedInvitation->forceFill([
                'status' => $targetStatus,
                'transition_reason_code' => $reasonCode,
                'status_changed_at' => now()->utc(),
            ])->save();

            if ($targetStatus === MissionInvitationStatus::Approved && $lockedInvitation->crusade_id === null) {
                $this->linkPlanningCrusade($lockedInvitation);
            }

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

            return $lockedInvitation->fresh(['crusade:id,public_id,name']) ?? $lockedInvitation;
        }, attempts: 3);
    }

    private function linkPlanningCrusade(MissionInvitation $invitation): void
    {
        $application = is_array($invitation->application_data) ? $invitation->application_data : [];
        $title = Str::squish((string) ($application['title'] ?? $application['event_title'] ?? ''));
        if ($title === '') {
            $title = 'Mission request '.$invitation->public_id;
        }

        $startsAt = null;
        if (isset($application['start']) && is_string($application['start']) && $application['start'] !== '') {
            try {
                $startsAt = CarbonImmutable::parse($application['start']);
            } catch (\Throwable) {
                $startsAt = null;
            }
        }

        $crusade = Crusade::query()->create([
            'name' => Str::substr($title, 0, 191),
            'purpose' => $invitation->purpose,
            'description' => $invitation->notes,
            'status' => CrusadeStatus::Approved,
            'location_id' => $invitation->requested_location_id,
            'starts_at' => $startsAt,
        ]);

        $invitation->crusade_id = $crusade->getKey();
        $invitation->save();
    }

    private function assertSafeCode(?string $code): void
    {
        if ($code !== null && (Str::length($code) > 100 || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $code))) {
            throw new InvalidArgumentException('Mission reason codes must be stable lowercase identifiers.');
        }
    }
}
