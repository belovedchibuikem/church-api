<?php

namespace App\Mission\Actions;

use App\Exceptions\MissionIdempotencyConflictException;
use App\Exceptions\MissionSoulAlreadyLinkedException;
use App\Mission\Data\CaptureMissionSoulData;
use App\Mission\MissionSoulJourneyStatus;
use App\Models\Crusade;
use App\Models\MissionSoulJourney;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CaptureMissionSoulAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(Crusade $crusade, CaptureMissionSoulData $data, ?User $actor = null): MissionSoulJourney
    {
        $normalizedNames = $this->validateAndNormalize($data);
        $scopeHash = $this->scopeHash($crusade, $data->idempotencyKey);
        $payloadFingerprint = $this->payloadFingerprint($data, $normalizedNames);

        return DB::transaction(function () use (
            $crusade,
            $data,
            $actor,
            $normalizedNames,
            $scopeHash,
            $payloadFingerprint,
        ): MissionSoulJourney {
            $existingRetry = MissionSoulJourney::query()
                ->lockForUpdate()
                ->where('capture_idempotency_scope_hash', $scopeHash)
                ->first();

            if ($existingRetry !== null) {
                if (! hash_equals((string) $existingRetry->capture_payload_fingerprint, $payloadFingerprint)) {
                    throw new MissionIdempotencyConflictException('The capture idempotency key was reused with different soul data.');
                }

                return $existingRetry;
            }

            $person = $data->person;

            if ($person !== null) {
                $alreadyLinked = MissionSoulJourney::query()
                    ->lockForUpdate()
                    ->whereBelongsTo($crusade)
                    ->whereBelongsTo($person)
                    ->exists();

                if ($alreadyLinked) {
                    throw new MissionSoulAlreadyLinkedException('The canonical Person is already linked to this crusade.');
                }
            } else {
                $person = Person::query()->create();
                $person->profile()->create($normalizedNames);
            }

            $journey = new MissionSoulJourney;
            $journey->forceFill([
                'crusade_id' => $crusade->getKey(),
                'person_id' => $person->getKey(),
                'status' => MissionSoulJourneyStatus::New,
                'capture_idempotency_scope_hash' => $scopeHash,
                'capture_payload_fingerprint' => $payloadFingerprint,
                'captured_at' => now()->utc(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'mission.soul.captured',
                actor: $actor,
                targetType: 'mission_soul_journey',
                targetId: $journey->public_id,
                scopeType: 'crusade',
                scopeId: $crusade->public_id,
                metadata: [
                    'person_public_id' => $person->public_id,
                    'created_canonical_person' => $data->person === null,
                ],
            ));

            return $journey;
        }, attempts: 3);
    }

    /** @return array<string, string|null> */
    private function validateAndNormalize(CaptureMissionSoulData $data): array
    {
        $idempotencyKey = trim($data->idempotencyKey);

        if (Str::length($idempotencyKey) < 8 || Str::length($idempotencyKey) > 191) {
            throw new InvalidArgumentException('Mission capture idempotency keys must contain 8 to 191 characters.');
        }

        $names = [
            'given_name' => $this->normalizeName($data->givenName),
            'middle_name' => $this->normalizeName($data->middleName),
            'family_name' => $this->normalizeName($data->familyName),
            'preferred_name' => $this->normalizeName($data->preferredName),
        ];
        $hasAnyNames = collect($names)->contains(static fn (?string $name): bool => $name !== null);

        if ($data->person !== null && $hasAnyNames) {
            throw new InvalidArgumentException('Provide either an existing canonical Person or new-person names, not both.');
        }

        if ($data->person === null && ($names['given_name'] === null || $names['family_name'] === null)) {
            throw new InvalidArgumentException('Given and family names are required when creating a canonical Person.');
        }

        return $names;
    }

    private function normalizeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $normalized = Str::squish($name);

        if ($normalized === '' || Str::length($normalized) > 100) {
            throw new InvalidArgumentException('Mission soul names must contain 1 to 100 characters.');
        }

        return $normalized;
    }

    private function scopeHash(Crusade $crusade, string $idempotencyKey): string
    {
        return hash_hmac('sha256', "mission.soul.capture|{$crusade->getKey()}|".trim($idempotencyKey), (string) config('app.key'));
    }

    /** @param array<string, string|null> $names */
    private function payloadFingerprint(CaptureMissionSoulData $data, array $names): string
    {
        $payload = $data->person === null
            ? ['new_person' => array_map(static fn (?string $name): ?string => $name === null ? null : Str::lower($name), $names)]
            : ['person_public_id' => $data->person->public_id];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
