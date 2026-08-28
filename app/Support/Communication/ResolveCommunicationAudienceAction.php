<?php

namespace App\Support\Communication;

use App\Communication\CommunicationBroadcastStatus;
use App\Communication\CommunicationRecipientStatus;
use App\Exceptions\CommunicationInvalidStateException;
use App\Models\CommunicationBroadcast;
use App\Models\CommunicationRecipient;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Communication\Contracts\CommunicationConsentPolicy;
use App\Support\Communication\Contracts\GuardianCommunicationPolicy;
use App\Support\Communication\Queries\CommunicationAudienceCandidateQuery;
use Illuminate\Support\Facades\DB;

class ResolveCommunicationAudienceAction
{
    public function __construct(
        private CommunicationAudienceCandidateQuery $candidateQuery,
        private CommunicationConsentPolicy $consentPolicy,
        private GuardianCommunicationPolicy $guardianPolicy,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(CommunicationBroadcast $broadcast, User $actor): CommunicationBroadcast
    {
        return DB::transaction(function () use ($broadcast, $actor): CommunicationBroadcast {
            $locked = CommunicationBroadcast::query()
                ->with(['audience.rules', 'template'])
                ->lockForUpdate()
                ->findOrFail($broadcast->getKey());

            if ($locked->status === CommunicationBroadcastStatus::Prepared) {
                return $locked;
            }

            if ($locked->status !== CommunicationBroadcastStatus::Draft) {
                throw new CommunicationInvalidStateException('Only draft broadcasts may have their audience resolved.');
            }

            $purpose = new CommunicationPurpose($locked->purpose);
            $eligibleCount = 0;
            $suppressedCount = 0;

            foreach ($this->candidateQuery->resolve($locked->audience) as $candidate) {
                $person = $candidate->person;

                if ($person === null) {
                    continue;
                }

                $consent = $this->consentPolicy->decide($person, $locked->channel, $purpose);
                $guardian = $this->guardianPolicy->decide($person, $locked->channel);
                $eligible = $consent->allowed && $guardian->allowed;
                $reasonCode = ! $consent->allowed
                    ? $consent->reasonCode
                    : ($guardian->allowed ? 'eligible' : $guardian->reasonCode);

                $recipient = (new CommunicationRecipient)->forceFill([
                    'communication_broadcast_id' => $locked->getKey(),
                    'person_id' => $person->getKey(),
                    'user_id' => $candidate->getKey(),
                    'status' => $eligible
                        ? CommunicationRecipientStatus::Eligible
                        : CommunicationRecipientStatus::Suppressed,
                    'reason_code' => $reasonCode,
                    'resolved_at' => now()->utc(),
                ]);
                $recipient->save();

                $eligible ? $eligibleCount++ : $suppressedCount++;
            }

            $locked->forceFill([
                'status' => CommunicationBroadcastStatus::Prepared,
                'prepared_at' => now()->utc(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'communications.broadcast.audience_resolved',
                actor: $actor,
                targetType: 'communication_broadcast',
                targetId: $locked->public_id,
                metadata: [
                    'eligible_count' => $eligibleCount,
                    'suppressed_count' => $suppressedCount,
                    'channel' => $locked->channel->value,
                    'purpose' => $locked->purpose,
                ],
            ));

            return $locked->refresh();
        }, attempts: 3);
    }
}
