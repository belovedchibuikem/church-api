<?php

namespace App\Finance\Actions;

use App\Exceptions\PaymentVerificationException;
use App\Finance\Contracts\WebhookVerifier;
use App\Finance\Data\PaymentWebhookEnvelope;
use App\Finance\RejectAllWebhookVerifier;
use App\Models\PaymentDispute;
use App\Models\PaymentTransaction;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RecordPaymentDisputeAction
{
    private readonly WebhookVerifier $verifier;

    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent, ?WebhookVerifier $verifier = null)
    {
        $this->verifier = $verifier ?? new RejectAllWebhookVerifier;
    }

    public function handle(PaymentWebhookEnvelope $envelope): PaymentDispute
    {
        $verified = $this->verifier->verify($envelope);
        if ($verified === null || ! str_starts_with($verified->type, 'dispute_') || $verified->disputeCaseId === null || $verified->disputeStatus === null || $verified->reasonCode === null) {
            throw new PaymentVerificationException('The dispute webhook was not verified.');
        }
        $eventHash = hash_hmac('sha256', "{$verified->providerCode}|{$verified->eventId}", (string) config('app.key'));
        $referenceHash = hash_hmac('sha256', "{$verified->providerCode}|{$verified->providerReference}", (string) config('app.key'));

        try {
            return DB::transaction(function () use ($verified, $eventHash, $referenceHash): PaymentDispute {
                $retry = PaymentDispute::query()->where('provider_event_hash', $eventHash)->first();
                if ($retry !== null) {
                    return $retry;
                }
                $transaction = PaymentTransaction::query()->where('provider_reference_hash', $referenceHash)->firstOrFail();
                $dispute = new PaymentDispute;
                $dispute->forceFill(['payment_transaction_id' => $transaction->getKey(), 'provider_event_hash' => $eventHash, 'dispute_case_hash' => hash_hmac('sha256', "{$verified->providerCode}|{$verified->disputeCaseId}", (string) config('app.key')), 'status' => $verified->disputeStatus, 'reason_code' => $verified->reasonCode, 'amount_minor' => $verified->amountMinor, 'occurred_at' => $verified->occurredAt])->save();
                $this->recordAuditEvent->handle(new AuditEventData(action: 'finance.dispute.recorded', targetType: 'payment_dispute', targetId: $dispute->public_id, metadata: ['status' => $dispute->status->value, 'reason_code' => $dispute->reason_code, 'amount_minor' => $dispute->amount_minor, 'currency' => strtoupper($verified->currency)]));

                return $dispute;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existingDispute = PaymentDispute::query()->where('provider_event_hash', $eventHash)->first();

            if ($existingDispute === null) {
                throw $exception;
            }

            return $existingDispute;
        }
    }
}
