<?php

namespace App\Finance\Actions;

use App\Exceptions\PaymentGovernanceDeniedException;
use App\Finance\Contracts\PaymentGovernancePolicy;
use App\Finance\PaymentIntentStatus;
use App\Models\EventRegistration;
use App\Models\MinistryEvent;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreatePaymentIntentAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PaymentGovernancePolicy $governance,
    ) {}

    public function handle(EventRegistration $registration, string $idempotencyKey, ?User $actor = null): PaymentIntent
    {
        $key = trim($idempotencyKey);
        if (Str::length($key) < 8 || Str::length($key) > 191) {
            throw new InvalidArgumentException('Payment idempotency keys must contain 8 to 191 characters.');
        }
        $scopeHash = hash_hmac('sha256', "payment.intent|{$registration->getKey()}|{$key}", (string) config('app.key'));

        return DB::transaction(function () use ($registration, $actor, $scopeHash): PaymentIntent {
            $lockedRegistration = EventRegistration::query()->lockForUpdate()->findOrFail($registration->getKey());
            $lockedEvent = MinistryEvent::query()->lockForUpdate()->findOrFail($lockedRegistration->ministry_event_id);
            $amountMinor = (int) ($lockedEvent->fee_amount_minor ?? 0);
            $currency = strtoupper((string) $lockedEvent->fee_currency);

            if ($amountMinor < 1 || ! preg_match('/\A[A-Z]{3}\z/', $currency)) {
                throw new InvalidArgumentException('The event does not define a valid server-side fee.');
            }

            if (! $this->governance->allowsPaymentIntent('event_payment', $currency, $lockedRegistration->person()->first())) {
                throw new PaymentGovernanceDeniedException('Payment governance has not enabled this payment intent.');
            }

            $fingerprint = hash('sha256', "{$lockedRegistration->public_id}|{$amountMinor}|{$currency}");
            $retry = PaymentIntent::query()->lockForUpdate()->where('idempotency_scope_hash', $scopeHash)->first();

            if ($retry !== null) {
                if (! hash_equals($retry->payload_fingerprint, $fingerprint)) {
                    throw new InvalidArgumentException('Payment idempotency conflict.');
                }

                return $retry;
            }

            $existing = PaymentIntent::query()
                ->lockForUpdate()
                ->where('event_registration_id', $lockedRegistration->getKey())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $intent = new PaymentIntent;
            $intent->forceFill(['payer_person_id' => $lockedRegistration->person_id, 'event_registration_id' => $lockedRegistration->getKey(), 'purpose_code' => 'event_payment', 'amount_minor' => $amountMinor, 'currency' => $currency, 'status' => PaymentIntentStatus::PendingProvider, 'idempotency_scope_hash' => $scopeHash, 'payload_fingerprint' => $fingerprint])->save();
            $this->recordAuditEvent->handle(new AuditEventData(action: 'finance.payment_intent.created', actor: $actor, targetType: 'payment_intent', targetId: $intent->public_id, scopeType: 'ministry_event', scopeId: $lockedEvent->public_id, metadata: ['purpose_code' => 'event_payment', 'amount_minor' => $amountMinor, 'currency' => $currency]));

            return $intent;
        }, attempts: 3);
    }
}
