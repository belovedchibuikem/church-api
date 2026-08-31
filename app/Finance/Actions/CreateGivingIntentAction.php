<?php

namespace App\Finance\Actions;

use App\Exceptions\PaymentGovernanceDeniedException;
use App\Files\FileAssetStatus;
use App\Finance\Contracts\PaymentGateway;
use App\Finance\Contracts\PaymentGovernancePolicy;
use App\Finance\GivingPurpose;
use App\Finance\PaymentIntentStatus;
use App\Models\FileAsset;
use App\Models\PaymentIntent;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateGivingIntentAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PaymentGovernancePolicy $governance,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * @return array{intent: PaymentIntent, client_payload: array<string, mixed>, provider_code: string}
     */
    public function handle(
        Person $payer,
        int $amountMinor,
        string $currency,
        string $idempotencyKey,
        string $purposeCode,
        ?User $actor = null,
        ?FileAsset $proof = null,
    ): array {
        $key = trim($idempotencyKey);
        if (Str::length($key) < 8 || Str::length($key) > 191) {
            throw new InvalidArgumentException('Payment idempotency keys must contain 8 to 191 characters.');
        }

        $currency = strtoupper(trim($currency));
        $purposeCode = strtolower(trim($purposeCode));
        if (! GivingPurpose::isMemberGiving($purposeCode)) {
            throw new InvalidArgumentException('Choose Tithe or Offering (or another giving purpose). Tithe and Offering are separate gifts.');
        }
        if ($amountMinor < 1 || ! preg_match('/\A[A-Z]{3}\z/', $currency)) {
            throw new InvalidArgumentException('A valid amount_minor and ISO currency are required.');
        }
        if ($proof !== null) {
            $this->assertOwnedProof($proof, $payer);
        }

        $scopeHash = hash_hmac(
            'sha256',
            "payment.giving|{$payer->getKey()}|{$key}",
            (string) config('app.key'),
        );

        return DB::transaction(function () use ($payer, $amountMinor, $currency, $purposeCode, $actor, $proof, $scopeHash): array {
            if (! $this->governance->allowsPaymentIntent($purposeCode, $currency, $payer)) {
                throw new PaymentGovernanceDeniedException('Payment governance has not enabled giving payment intents.');
            }

            $fingerprint = hash('sha256', "{$purposeCode}|{$payer->public_id}|{$amountMinor}|{$currency}");
            $retry = PaymentIntent::query()->lockForUpdate()->where('idempotency_scope_hash', $scopeHash)->first();

            if ($retry !== null) {
                if (! hash_equals($retry->payload_fingerprint, $fingerprint)) {
                    throw new InvalidArgumentException('Payment idempotency conflict.');
                }

                $initiated = $this->gateway->initiate($retry);

                return [
                    'intent' => $retry,
                    'client_payload' => $initiated['client_payload'],
                    'provider_code' => $this->gateway->providerCode(),
                ];
            }

            $intent = new PaymentIntent;
            $intent->forceFill([
                'payer_person_id' => $payer->getKey(),
                'event_registration_id' => null,
                'purpose_code' => $purposeCode,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'status' => PaymentIntentStatus::PendingProvider,
                'idempotency_scope_hash' => $scopeHash,
                'payload_fingerprint' => $fingerprint,
                'proof_file_asset_id' => $proof?->getKey(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'finance.payment_intent.created',
                actor: $actor,
                targetType: 'payment_intent',
                targetId: $intent->public_id,
                scopeType: 'person',
                scopeId: $payer->public_id,
                metadata: [
                    'purpose_code' => $purposeCode,
                    'amount_minor' => $amountMinor,
                    'currency' => $currency,
                    'provider_code' => $this->gateway->providerCode(),
                ],
            ));

            $initiated = $this->gateway->initiate($intent);

            return [
                'intent' => $intent,
                'client_payload' => $initiated['client_payload'],
                'provider_code' => $this->gateway->providerCode(),
            ];
        }, attempts: 3);
    }

    private function assertOwnedProof(FileAsset $proof, Person $payer): void
    {
        if ((int) $proof->owner_person_id !== (int) $payer->getKey()) {
            throw new InvalidArgumentException('Payment receipt must be uploaded by the giver.');
        }
        if ($proof->purpose !== GivingPurpose::PROOF_FILE_PURPOSE) {
            throw new InvalidArgumentException('Upload a payment receipt (purpose payment.proof).');
        }
        if ($proof->status === FileAssetStatus::Rejected || $proof->deleted_at !== null) {
            throw new InvalidArgumentException('The uploaded payment receipt was rejected. Upload a new file.');
        }
    }
}
