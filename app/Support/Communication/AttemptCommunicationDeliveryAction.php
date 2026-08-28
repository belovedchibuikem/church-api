<?php

namespace App\Support\Communication;

use App\Communication\CommunicationBroadcastStatus;
use App\Communication\CommunicationDeliveryStatus;
use App\Communication\CommunicationRecipientStatus;
use App\Exceptions\CommunicationConsentDeniedException;
use App\Exceptions\CommunicationInvalidStateException;
use App\Models\CommunicationDeliveryAttempt;
use App\Models\CommunicationRecipient;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Communication\Contracts\CommunicationDeliveryGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class AttemptCommunicationDeliveryAction
{
    public function __construct(
        private CommunicationDeliveryGateway $deliveryGateway,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        CommunicationRecipient $recipient,
        string $idempotencyKey,
        User $actor,
    ): CommunicationDeliveryAttempt {
        if ($idempotencyKey === '' || Str::length($idempotencyKey) > 255) {
            throw new InvalidArgumentException('Delivery idempotency keys must contain 1 to 255 characters.');
        }

        $idempotencyKeyHash = hash_hmac('sha256', $idempotencyKey, $this->hashKey());
        $attempt = DB::transaction(function () use ($recipient, $idempotencyKeyHash, $actor): CommunicationDeliveryAttempt {
            $lockedRecipient = CommunicationRecipient::query()
                ->with('broadcast.template')
                ->lockForUpdate()
                ->findOrFail($recipient->getKey());

            if ($lockedRecipient->status !== CommunicationRecipientStatus::Eligible) {
                throw new CommunicationConsentDeniedException('Suppressed recipients cannot be delivered communications.');
            }

            if ($lockedRecipient->broadcast->status !== CommunicationBroadcastStatus::Prepared) {
                throw new CommunicationInvalidStateException('Only prepared broadcasts may be delivered.');
            }

            $existing = CommunicationDeliveryAttempt::query()
                ->whereBelongsTo($lockedRecipient, 'recipient')
                ->where('idempotency_key_hash', $idempotencyKeyHash)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $attempt = (new CommunicationDeliveryAttempt)->forceFill([
                'communication_recipient_id' => $lockedRecipient->getKey(),
                'channel' => $lockedRecipient->broadcast->channel,
                'status' => CommunicationDeliveryStatus::Pending,
                'result_code' => null,
                'idempotency_key_hash' => $idempotencyKeyHash,
                'attempted_at' => null,
            ]);
            $attempt->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'communications.delivery.requested',
                actor: $actor,
                targetType: 'communication_delivery_attempt',
                targetId: $attempt->public_id,
                metadata: ['channel' => $lockedRecipient->broadcast->channel->value],
            ));

            return $attempt;
        }, attempts: 3);

        if ($attempt->status !== CommunicationDeliveryStatus::Pending) {
            return $attempt;
        }

        $attempt->loadMissing('recipient.broadcast.template');

        try {
            $result = $this->deliveryGateway->attempt(
                $attempt->recipient,
                $attempt->recipient->broadcast->template,
                $idempotencyKeyHash,
            );
        } catch (Throwable) {
            $result = new CommunicationDeliveryResult(
                CommunicationDeliveryStatus::Failed,
                'gateway_exception',
            );
        }

        return DB::transaction(function () use ($attempt, $result, $actor): CommunicationDeliveryAttempt {
            $lockedAttempt = CommunicationDeliveryAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->getKey());

            if ($lockedAttempt->status !== CommunicationDeliveryStatus::Pending) {
                return $lockedAttempt;
            }

            $lockedAttempt->forceFill([
                'status' => $result->status,
                'result_code' => $result->resultCode,
                'attempted_at' => now()->utc(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'communications.delivery.completed',
                actor: $actor,
                targetType: 'communication_delivery_attempt',
                targetId: $lockedAttempt->public_id,
                metadata: [
                    'status' => $result->status->value,
                    'result_code' => $result->resultCode,
                    'channel' => $lockedAttempt->channel->value,
                ],
            ));

            return $lockedAttempt;
        }, attempts: 3);
    }

    private function hashKey(): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new InvalidArgumentException('The application key is required for delivery idempotency.');
        }

        return $key;
    }
}
