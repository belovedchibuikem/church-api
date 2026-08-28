<?php

namespace App\Finance\Actions;

use App\Events\EventRegistrationStatus;
use App\Exceptions\PaymentVerificationException;
use App\Finance\Contracts\WebhookVerifier;
use App\Finance\Data\PaymentWebhookEnvelope;
use App\Finance\PaymentIntentStatus;
use App\Finance\PaymentReconciliationStatus;
use App\Models\PaymentIntent;
use App\Models\PaymentReceipt;
use App\Models\PaymentReconciliation;
use App\Models\PaymentTransaction;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReconcilePaymentWebhookAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly WebhookVerifier $verifier,
    ) {}

    public function handle(PaymentWebhookEnvelope $envelope): PaymentReconciliation
    {
        $verified = $this->verifier->verify($envelope);
        if ($verified === null || $verified->type !== 'payment_succeeded') {
            throw new PaymentVerificationException('The payment webhook was not verified as a successful payment.');
        }
        $eventHash = hash_hmac('sha256', "{$verified->providerCode}|{$verified->eventId}", (string) config('app.key'));

        try {
            return DB::transaction(function () use ($verified, $eventHash): PaymentReconciliation {
                $existingTransaction = PaymentTransaction::query()->where('provider_event_hash', $eventHash)->first();
                if ($existingTransaction !== null) {
                    return $existingTransaction->reconciliation()->firstOrFail();
                }
                $intent = PaymentIntent::query()->lockForUpdate()->where('public_id', $verified->paymentIntentPublicId)->firstOrFail();
                $transaction = new PaymentTransaction;
                $transaction->forceFill(['payment_intent_id' => $intent->getKey(), 'provider_code' => $verified->providerCode, 'provider_event_hash' => $eventHash, 'provider_reference_hash' => hash_hmac('sha256', "{$verified->providerCode}|{$verified->providerReference}", (string) config('app.key')), 'amount_minor' => $verified->amountMinor, 'currency' => strtoupper($verified->currency), 'occurred_at' => $verified->occurredAt])->save();
                $matched = $intent->amount_minor === $verified->amountMinor && $intent->currency === strtoupper($verified->currency);
                $reconciliation = new PaymentReconciliation;
                $reconciliation->forceFill(['payment_transaction_id' => $transaction->getKey(), 'status' => $matched ? PaymentReconciliationStatus::Matched : PaymentReconciliationStatus::Mismatch, 'reason_code' => $matched ? 'amount_currency_matched' : 'amount_or_currency_mismatch', 'reconciled_at' => now()->utc()])->save();
                if ($matched) {
                    $intent->forceFill(['status' => PaymentIntentStatus::Succeeded, 'succeeded_at' => now()->utc()])->save();
                    if ($intent->event_registration_id !== null) {
                        $intent->eventRegistration->forceFill(['status' => EventRegistrationStatus::Confirmed, 'confirmed_at' => now()->utc()])->save();
                    }
                    $receipt = new PaymentReceipt;
                    $receipt->forceFill(['payment_transaction_id' => $transaction->getKey(), 'receipt_number' => 'R-'.Str::ulid(), 'issued_at' => now()->utc()])->save();
                }
                $this->recordAuditEvent->handle(new AuditEventData(action: $matched ? 'finance.payment.reconciled' : 'finance.payment.reconciliation_mismatch', targetType: 'payment_transaction', targetId: $transaction->public_id, metadata: ['status' => $reconciliation->status->value, 'reason_code' => $reconciliation->reason_code]));

                return $reconciliation;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existingTransaction = PaymentTransaction::query()->where('provider_event_hash', $eventHash)->first();

            if ($existingTransaction === null) {
                throw $exception;
            }

            return $existingTransaction->reconciliation()->firstOrFail();
        }
    }
}
