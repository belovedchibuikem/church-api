<?php

namespace App\Mission\Actions;

use App\Exceptions\MissionAssignmentException;
use App\Exceptions\MissionIdempotencyConflictException;
use App\Exceptions\MissionJourneyStateException;
use App\Mission\MissionSoulJourneyStatus;
use App\Models\MentorAssignment;
use App\Models\MissionSoulJourney;
use App\Models\MissionTeamAssignment;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AssignSoulMentorAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        MissionSoulJourney $soulJourney,
        MissionTeamAssignment $mentorTeamAssignment,
        string $idempotencyKey,
        ?User $actor = null,
    ): MentorAssignment {
        $this->assertIdempotencyKey($idempotencyKey);
        $scopeHash = hash_hmac(
            'sha256',
            "mission.mentor.assign|{$soulJourney->getKey()}|".trim($idempotencyKey),
            (string) config('app.key'),
        );
        $payloadFingerprint = hash('sha256', (string) $mentorTeamAssignment->public_id);

        return DB::transaction(function () use (
            $soulJourney,
            $mentorTeamAssignment,
            $actor,
            $scopeHash,
            $payloadFingerprint,
        ): MentorAssignment {
            $existingRetry = MentorAssignment::query()
                ->lockForUpdate()
                ->where('idempotency_scope_hash', $scopeHash)
                ->first();

            if ($existingRetry !== null) {
                if (
                    $existingRetry->mission_soul_journey_id !== $soulJourney->getKey()
                    || ! hash_equals((string) $existingRetry->payload_fingerprint, $payloadFingerprint)
                ) {
                    throw new MissionIdempotencyConflictException('The mentor idempotency key was reused with different assignment data.');
                }

                return $existingRetry;
            }

            $lockedJourney = MissionSoulJourney::query()->lockForUpdate()->findOrFail($soulJourney->getKey());
            $lockedTeamAssignment = MissionTeamAssignment::query()->lockForUpdate()->findOrFail($mentorTeamAssignment->getKey());

            if ($lockedTeamAssignment->crusade_id !== $lockedJourney->crusade_id) {
                throw new MissionAssignmentException('A mentor team assignment must belong to the soul journey crusade.');
            }

            if ($lockedTeamAssignment->ended_at !== null) {
                throw new MissionAssignmentException('An ended mission team assignment cannot mentor a soul.');
            }

            if ($lockedTeamAssignment->person_id === $lockedJourney->person_id) {
                throw new MissionAssignmentException('A soul cannot be assigned as their own mentor.');
            }

            if ($lockedJourney->status !== MissionSoulJourneyStatus::New || $lockedJourney->closed_at !== null) {
                throw new MissionJourneyStateException('A mentor may only be assigned to a new, open soul journey.');
            }

            if (MentorAssignment::query()->whereBelongsTo($lockedJourney, 'soulJourney')->exists()) {
                throw new MissionAssignmentException('The soul journey already has a mentor assignment.');
            }

            $assignment = new MentorAssignment;
            $assignment->forceFill([
                'mission_soul_journey_id' => $lockedJourney->getKey(),
                'mission_team_assignment_id' => $lockedTeamAssignment->getKey(),
                'idempotency_scope_hash' => $scopeHash,
                'payload_fingerprint' => $payloadFingerprint,
                'assigned_at' => now()->utc(),
            ])->save();

            $lockedJourney->forceFill([
                'status' => MissionSoulJourneyStatus::MentorAssigned,
                'mentor_assigned_at' => now()->utc(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'mission.soul.mentor_assigned',
                actor: $actor,
                targetType: 'mission_soul_journey',
                targetId: $lockedJourney->public_id,
                scopeType: 'crusade',
                scopeId: $lockedJourney->crusade->public_id,
                metadata: [
                    'mentor_assignment_public_id' => $assignment->public_id,
                    'mentor_person_public_id' => $lockedTeamAssignment->person->public_id,
                ],
            ));

            return $assignment;
        }, attempts: 3);
    }

    private function assertIdempotencyKey(string $key): void
    {
        $length = Str::length(trim($key));

        if ($length < 8 || $length > 191) {
            throw new InvalidArgumentException('Mission mentor idempotency keys must contain 8 to 191 characters.');
        }
    }
}
