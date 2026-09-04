<?php

namespace App\Support\Church;

use App\Church\ChurchMembershipStatus;
use App\Finance\GivingPurpose;
use App\Finance\PaymentIntentStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\PaymentIntent;
use App\Models\PaymentReceipt;
use App\Models\PaymentTransaction;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordChurchGivingAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @return array{intent: PaymentIntent, transaction: PaymentTransaction, receipt: PaymentReceipt}
     */
    public function handle(
        Church $church,
        Person $payer,
        int $amountMinor,
        string $currency,
        string $purposeCode,
        string $idempotencyKey,
        ?User $actor = null,
        ?CarbonInterface $occurredAt = null,
        ?string $channel = null,
    ): array {
        $key = trim($idempotencyKey);
        if (Str::length($key) < 8 || Str::length($key) > 191) {
            throw new InvalidArgumentException('Payment idempotency keys must contain 8 to 191 characters.');
        }

        $currency = strtoupper(trim($currency));
        $purposeCode = strtolower(trim($purposeCode));
        $channel = strtolower(trim((string) $channel));
        if ($channel === '') {
            $channel = 'church_ledger';
        }
        if (! GivingPurpose::isMemberGiving($purposeCode)) {
            throw new InvalidArgumentException('Choose a valid giving purpose such as tithe or offering.');
        }
        if ($amountMinor < 1 || ! preg_match('/\A[A-Z]{3}\z/', $currency)) {
            throw new InvalidArgumentException('A valid amount_minor and ISO currency are required.');
        }

        $member = ChurchMembership::query()
            ->where('church_id', $church->getKey())
            ->where('person_id', $payer->getKey())
            ->where('status', ChurchMembershipStatus::Active)
            ->where('active_marker', 1)
            ->exists();
        if (! $member) {
            throw new InvalidArgumentException('Giving can only be recorded for an active member of this church.');
        }

        $scopeHash = hash_hmac(
            'sha256',
            "church.giving|{$church->getKey()}|{$payer->getKey()}|{$key}",
            (string) config('app.key'),
        );
        $fingerprint = hash('sha256', "{$purposeCode}|{$payer->public_id}|{$amountMinor}|{$currency}|{$channel}");
        $occurred = ($occurredAt ?? now())->utc();

        return DB::transaction(function () use (
            $church,
            $payer,
            $amountMinor,
            $currency,
            $purposeCode,
            $actor,
            $channel,
            $scopeHash,
            $fingerprint,
            $occurred,
        ): array {
            $retry = PaymentIntent::query()->lockForUpdate()->where('idempotency_scope_hash', $scopeHash)->first();
            if ($retry !== null) {
                if (! hash_equals((string) $retry->payload_fingerprint, $fingerprint)) {
                    throw new InvalidArgumentException('Payment idempotency conflict.');
                }
                $transaction = PaymentTransaction::query()->where('payment_intent_id', $retry->getKey())->firstOrFail();
                $receipt = PaymentReceipt::query()->where('payment_transaction_id', $transaction->getKey())->firstOrFail();

                return [
                    'intent' => $retry,
                    'transaction' => $transaction,
                    'receipt' => $receipt,
                ];
            }

            $intent = new PaymentIntent;
            $intent->forceFill([
                'payer_person_id' => $payer->getKey(),
                'event_registration_id' => null,
                'purpose_code' => $purposeCode,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'status' => PaymentIntentStatus::Succeeded,
                'idempotency_scope_hash' => $scopeHash,
                'payload_fingerprint' => $fingerprint,
                'succeeded_at' => $occurred,
            ])->save();

            $transaction = new PaymentTransaction;
            $transaction->forceFill([
                'payment_intent_id' => $intent->getKey(),
                'provider_code' => $channel,
                'provider_event_hash' => hash('sha256', 'church_ledger|'.$intent->public_id),
                'provider_reference_hash' => hash('sha256', 'church_'.$intent->public_id),
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'occurred_at' => $occurred,
                'created_at' => now()->utc(),
            ])->save();

            $receipt = new PaymentReceipt;
            $receipt->forceFill([
                'payment_transaction_id' => $transaction->getKey(),
                'receipt_number' => 'FHC-'.strtoupper(Str::substr((string) $intent->public_id, -10)),
                'issued_at' => $occurred,
                'created_at' => now()->utc(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'church.finance.giving_recorded',
                actor: $actor,
                targetType: 'payment_intent',
                targetId: $intent->public_id,
                scopeType: 'church',
                scopeId: $church->public_id,
                metadata: [
                    'purpose_code' => $purposeCode,
                    'amount_minor' => $amountMinor,
                    'currency' => $currency,
                    'payer_person_id' => $payer->public_id,
                    'channel' => $channel,
                ],
            ));

            return [
                'intent' => $intent,
                'transaction' => $transaction,
                'receipt' => $receipt,
            ];
        }, attempts: 3);
    }
}
