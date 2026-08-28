<?php

namespace App\Finance\Actions;

use App\Exceptions\PaymentGovernanceDeniedException;
use App\Finance\Contracts\PaymentGovernancePolicy;
use App\Finance\PaymentIntentStatus;
use App\Models\PaymentIntent;
use App\Models\PaymentReceipt;
use App\Models\PaymentTransaction;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class CompleteLocalGivingAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PaymentGovernancePolicy $governance,
    ) {}

    /**
     * @return array{intent: PaymentIntent, transaction: PaymentTransaction, receipt: PaymentReceipt}
     */
    public function handle(PaymentIntent $intent, Person $payer, User $actor): array
    {
        if ($intent->purpose_code !== 'giving') {
            throw new InvalidArgumentException('Only giving intents can be completed via the local gateway.');
        }

        if ((int) $intent->payer_person_id !== (int) $payer->getKey()) {
            throw new InvalidArgumentException('Giving intent does not belong to the authenticated person.');
        }

        if (config('finance.gateway') !== 'local_manual') {
            throw new LogicException('Local giving completion requires PAYMENT_GATEWAY=local_manual.');
        }

        if (! $this->governance->allowsPaymentIntent('giving', (string) $intent->currency, $payer)) {
            throw new PaymentGovernanceDeniedException('Payment governance has not enabled local giving completion.');
        }

        return DB::transaction(function () use ($intent, $payer, $actor): array {
            $locked = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->getKey());

            if ($locked->status === PaymentIntentStatus::Succeeded) {
                $transaction = PaymentTransaction::query()->where('payment_intent_id', $locked->getKey())->firstOrFail();
                $receipt = PaymentReceipt::query()->where('payment_transaction_id', $transaction->getKey())->firstOrFail();

                return [
                    'intent' => $locked,
                    'transaction' => $transaction,
                    'receipt' => $receipt,
                ];
            }

            if ($locked->status !== PaymentIntentStatus::PendingProvider) {
                throw new InvalidArgumentException('Only pending_provider giving intents can be completed.');
            }

            $reference = 'local_'.Str::lower((string) $locked->public_id);
            $eventHash = hash('sha256', 'local_manual|complete|'.$locked->public_id);
            $referenceHash = hash('sha256', $reference);

            $transaction = new PaymentTransaction;
            $transaction->forceFill([
                'payment_intent_id' => $locked->getKey(),
                'provider_code' => 'local_manual',
                'provider_event_hash' => $eventHash,
                'provider_reference_hash' => $referenceHash,
                'amount_minor' => $locked->amount_minor,
                'currency' => $locked->currency,
                'occurred_at' => now()->utc(),
                'created_at' => now()->utc(),
            ])->save();

            $receipt = new PaymentReceipt;
            $receipt->forceFill([
                'payment_transaction_id' => $transaction->getKey(),
                'receipt_number' => 'FHC-'.strtoupper(Str::substr((string) $locked->public_id, -10)),
                'issued_at' => now()->utc(),
                'created_at' => now()->utc(),
            ])->save();

            $locked->forceFill([
                'status' => PaymentIntentStatus::Succeeded,
                'succeeded_at' => now()->utc(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'finance.giving.local_completed',
                actor: $actor,
                targetType: 'payment_intent',
                targetId: $locked->public_id,
                scopeType: 'person',
                scopeId: $payer->public_id,
                metadata: [
                    'transaction_id' => $transaction->public_id,
                    'receipt_id' => $receipt->public_id,
                ],
            ));

            return [
                'intent' => $locked->fresh(),
                'transaction' => $transaction,
                'receipt' => $receipt,
            ];
        }, attempts: 3);
    }
}
