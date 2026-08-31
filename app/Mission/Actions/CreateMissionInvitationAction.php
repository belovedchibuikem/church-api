<?php

namespace App\Mission\Actions;

use App\Mission\MissionInvitationStatus;
use App\Models\Crusade;
use App\Models\Location;
use App\Models\MissionInvitation;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateMissionInvitationAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        ?Crusade $crusade,
        Person $requester,
        ?Location $location,
        User $actor,
        array $attributes = [],
    ): MissionInvitation {
        $idempotencyHash = $this->idempotencyHash($attributes['idempotency_key'] ?? null, $requester);

        return DB::transaction(function () use ($crusade, $requester, $location, $actor, $attributes, $idempotencyHash): MissionInvitation {
            if ($idempotencyHash !== null) {
                $existing = MissionInvitation::query()
                    ->lockForUpdate()
                    ->where('idempotency_key_hash', $idempotencyHash)
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            $lockedCrusade = $crusade === null ? null : Crusade::query()->lockForUpdate()->findOrFail($crusade->getKey());
            $lockedRequester = Person::query()->lockForUpdate()->findOrFail($requester->getKey());
            $lockedLocation = $location === null ? null : Location::query()->lockForUpdate()->findOrFail($location->getKey());

            $invitation = (new MissionInvitation)->forceFill([
                'crusade_id' => $lockedCrusade?->getKey(),
                'requester_person_id' => $lockedRequester->getKey(),
                'requested_location_id' => $lockedLocation?->getKey(),
                'purpose' => $this->optionalString($attributes['purpose'] ?? null, 500),
                'expected_attendance' => isset($attributes['expected_attendance']) ? (int) $attributes['expected_attendance'] : null,
                'notes' => $this->optionalString($attributes['notes'] ?? null, 10000),
                'application_data' => $attributes['application_data'] ?? null,
                'idempotency_key_hash' => $idempotencyHash,
                'status' => MissionInvitationStatus::Received,
                'status_changed_at' => now()->utc(),
            ]);
            $invitation->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'mission.invitation.created',
                actor: $actor,
                targetType: 'mission_invitation',
                targetId: $invitation->public_id,
                scopeType: $lockedCrusade === null ? null : 'crusade',
                scopeId: $lockedCrusade?->public_id,
                metadata: array_filter([
                    'requester_person_id' => $lockedRequester->public_id,
                    'requested_location_id' => $lockedLocation?->public_id,
                ]),
            ));

            return $invitation->fresh(['crusade:id,public_id,name', 'requester:id,public_id', 'requestedLocation:id,public_id,name']) ?? $invitation;
        }, attempts: 3);
    }

    private function idempotencyHash(mixed $key, Person $requester): ?string
    {
        if (! is_string($key) || trim($key) === '') {
            return null;
        }

        return hash_hmac('sha256', 'mission.invitation|'.$requester->getKey().'|'.trim($key), (string) config('app.key'));
    }

    private function optionalString(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $normalized = Str::squish((string) $value);
        if ($normalized === '') {
            return null;
        }

        return Str::length($normalized) > $max ? Str::substr($normalized, 0, $max) : $normalized;
    }
}
