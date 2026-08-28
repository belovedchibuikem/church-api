<?php

namespace App\Support\Communication;

use App\Communication\CommunicationBroadcastStatus;
use App\Communication\CommunicationChannel;
use App\Communication\CommunicationKind;
use App\Exceptions\CommunicationIdempotencyConflictException;
use App\Models\CommunicationAudience;
use App\Models\CommunicationBroadcast;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PrepareCommunicationBroadcastAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        CommunicationTemplate $template,
        CommunicationAudience $audience,
        CommunicationKind $kind,
        CommunicationChannel $channel,
        CommunicationPurpose $purpose,
        string $idempotencyKey,
        User $actor,
        ?CarbonInterface $scheduledAt = null,
    ): CommunicationBroadcast {
        if ($idempotencyKey === '' || Str::length($idempotencyKey) > 255) {
            throw new InvalidArgumentException('Broadcast idempotency keys must contain 1 to 255 characters.');
        }

        $idempotencyKeyHash = hash_hmac('sha256', $idempotencyKey, $this->hashKey());
        $scheduledAt = $scheduledAt?->toImmutable()->utc();

        return DB::transaction(function () use (
            $template,
            $audience,
            $kind,
            $channel,
            $purpose,
            $idempotencyKeyHash,
            $actor,
            $scheduledAt,
        ): CommunicationBroadcast {
            $lockedTemplate = CommunicationTemplate::query()->lockForUpdate()->findOrFail($template->getKey());
            $lockedAudience = CommunicationAudience::query()->lockForUpdate()->findOrFail($audience->getKey());
            $existing = CommunicationBroadcast::query()
                ->where('idempotency_key_hash', $idempotencyKeyHash)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (
                    $existing->communication_template_id !== $lockedTemplate->getKey()
                    || $existing->communication_audience_id !== $lockedAudience->getKey()
                    || $existing->kind !== $kind
                    || $existing->channel !== $channel
                    || $existing->purpose !== $purpose->value
                    || ! $this->sameInstant($existing->scheduled_at, $scheduledAt)
                ) {
                    throw new CommunicationIdempotencyConflictException;
                }

                return $existing;
            }

            if ($lockedTemplate->channel !== $channel) {
                throw new InvalidArgumentException('The communication template channel must match the broadcast channel.');
            }

            $broadcast = (new CommunicationBroadcast)->forceFill([
                'communication_template_id' => $lockedTemplate->getKey(),
                'communication_audience_id' => $lockedAudience->getKey(),
                'kind' => $kind,
                'channel' => $channel,
                'purpose' => $purpose->value,
                'status' => CommunicationBroadcastStatus::Draft,
                'scheduled_at' => $scheduledAt,
                'prepared_at' => null,
                'idempotency_key_hash' => $idempotencyKeyHash,
                'created_by_user_id' => $actor->getKey(),
            ]);
            $broadcast->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'communications.broadcast.created',
                actor: $actor,
                targetType: 'communication_broadcast',
                targetId: $broadcast->public_id,
                metadata: [
                    'audience_id' => $lockedAudience->public_id,
                    'template_code' => $lockedTemplate->code,
                    'kind' => $kind->value,
                    'channel' => $channel->value,
                    'purpose' => $purpose->value,
                ],
            ));

            return $broadcast;
        }, attempts: 3);
    }

    private function sameInstant(?CarbonInterface $first, ?CarbonInterface $second): bool
    {
        if ($first === null || $second === null) {
            return $first === null && $second === null;
        }

        return $first->equalTo($second);
    }

    private function hashKey(): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new InvalidArgumentException('The application key is required for broadcast idempotency.');
        }

        return $key;
    }
}
