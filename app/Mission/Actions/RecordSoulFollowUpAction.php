<?php

namespace App\Mission\Actions;

use App\Exceptions\MissionAssignmentException;
use App\Exceptions\MissionIdempotencyConflictException;
use App\Exceptions\MissionJourneyStateException;
use App\Mission\MissionSoulJourneyStatus;
use App\Models\FollowUpInteraction;
use App\Models\MentorAssignment;
use App\Models\MissionSoulJourney;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordSoulFollowUpAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        MissionSoulJourney $soulJourney,
        MentorAssignment $mentorAssignment,
        string $channelCode,
        string $outcomeCode,
        DateTimeInterface $occurredAt,
        string $idempotencyKey,
        ?User $actor = null,
    ): FollowUpInteraction {
        $this->assertSafeCode($channelCode, 'channel');
        $this->assertSafeCode($outcomeCode, 'outcome');
        $this->assertIdempotencyKey($idempotencyKey);
        $occurredAt = CarbonImmutable::instance($occurredAt)->utc();
        $scopeHash = hash_hmac(
            'sha256',
            "mission.follow_up.record|{$soulJourney->getKey()}|".trim($idempotencyKey),
            (string) config('app.key'),
        );
        $payloadFingerprint = hash('sha256', json_encode([
            'mentor_assignment' => $mentorAssignment->public_id,
            'channel_code' => $channelCode,
            'outcome_code' => $outcomeCode,
            'occurred_at' => $occurredAt->format('Y-m-d\TH:i:s.u\Z'),
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $soulJourney,
            $mentorAssignment,
            $channelCode,
            $outcomeCode,
            $occurredAt,
            $actor,
            $scopeHash,
            $payloadFingerprint,
        ): FollowUpInteraction {
            $existingRetry = FollowUpInteraction::query()
                ->lockForUpdate()
                ->where('idempotency_scope_hash', $scopeHash)
                ->first();

            if ($existingRetry !== null) {
                if (! hash_equals((string) $existingRetry->payload_fingerprint, $payloadFingerprint)) {
                    throw new MissionIdempotencyConflictException('The follow-up idempotency key was reused with different interaction data.');
                }

                return $existingRetry;
            }

            $lockedJourney = MissionSoulJourney::query()->lockForUpdate()->findOrFail($soulJourney->getKey());
            $lockedMentorAssignment = MentorAssignment::query()->lockForUpdate()->findOrFail($mentorAssignment->getKey());

            if ($lockedMentorAssignment->mission_soul_journey_id !== $lockedJourney->getKey()) {
                throw new MissionAssignmentException('The mentor assignment does not belong to this soul journey.');
            }

            if ($lockedMentorAssignment->ended_at !== null) {
                throw new MissionAssignmentException('An ended mentor assignment cannot record follow-up.');
            }

            if (! $lockedJourney->status->acceptsFollowUp() || $lockedJourney->closed_at !== null) {
                throw new MissionJourneyStateException('Follow-up cannot be recorded for the soul journey in its current state.');
            }

            $interaction = new FollowUpInteraction;
            $interaction->forceFill([
                'mission_soul_journey_id' => $lockedJourney->getKey(),
                'mentor_assignment_id' => $lockedMentorAssignment->getKey(),
                'channel_code' => $channelCode,
                'outcome_code' => $outcomeCode,
                'idempotency_scope_hash' => $scopeHash,
                'payload_fingerprint' => $payloadFingerprint,
                'occurred_at' => $occurredAt,
            ])->save();

            $lockedJourney->forceFill([
                'status' => MissionSoulJourneyStatus::FollowUpActive,
                'last_follow_up_at' => $occurredAt,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'mission.soul.follow_up_recorded',
                actor: $actor,
                targetType: 'mission_soul_journey',
                targetId: $lockedJourney->public_id,
                scopeType: 'crusade',
                scopeId: $lockedJourney->crusade->public_id,
                metadata: [
                    'interaction_public_id' => $interaction->public_id,
                    'channel_code' => $channelCode,
                    'outcome_code' => $outcomeCode,
                ],
            ));

            return $interaction;
        }, attempts: 3);
    }

    private function assertSafeCode(string $code, string $name): void
    {
        if (Str::length($code) > 100 || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $code)) {
            throw new InvalidArgumentException("Mission {$name} codes must be stable lowercase identifiers.");
        }
    }

    private function assertIdempotencyKey(string $key): void
    {
        $length = Str::length(trim($key));

        if ($length < 8 || $length > 191) {
            throw new InvalidArgumentException('Mission follow-up idempotency keys must contain 8 to 191 characters.');
        }
    }
}
