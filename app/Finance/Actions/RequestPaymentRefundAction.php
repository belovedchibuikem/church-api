<?php

namespace App\Finance\Actions;

use App\Exceptions\PaymentGovernanceDeniedException;
use App\Finance\Contracts\PaymentGovernancePolicy;
use App\Finance\PaymentReconciliationStatus;
use App\Finance\PaymentRefundStatus;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RequestPaymentRefundAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PaymentGovernancePolicy $governance,
    ) {}

    public function handle(PaymentTransaction $transaction, int $amountMinor, string $reasonCode, string $idempotencyKey, ?User $actor = null): PaymentRefund
    {
        if ($amountMinor < 1 || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $reasonCode)) {
            throw new InvalidArgumentException('Refund amount and reason code are invalid.');
        }

        $key = trim($idempotencyKey);
        if (Str::length($key) < 8 || Str::length($key) > 191) {
            throw new InvalidArgumentException('Refund idempotency keys must contain 8 to 191 characters.');
        }

        $scopeHash = hash_hmac('sha256', "payment.refund|{$transaction->getKey()}|{$key}", (string) config('app.key'));
        $fingerprint = hash('sha256', "{$amountMinor}|{$reasonCode}");

        return DB::transaction(function () use ($transaction, $amountMinor, $reasonCode, $actor, $scopeHash, $fingerprint): PaymentRefund {
            $locked = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());

            if (! $this->governance->allowsRefund($locked, $amountMinor, $actor)) {
                throw new PaymentGovernanceDeniedException('Refund governance has not authorized this request.');
            }

            $retry = PaymentRefund::query()->lockForUpdate()->where('idempotency_scope_hash', $scopeHash)->first();
            if ($retry !== null) {
                if (! hash_equals($retry->payload_fingerprint, $fingerprint)) {
                    throw new DomainException('Refund idempotency conflict.');
                }

                return $retry;
            }

            if ($locked->reconciliation()->first()?->status !== PaymentReconciliationStatus::Matched) {
                throw new DomainException('Only reconciled payments may be refunded.');
            }
            $alreadyRequested = (int) PaymentRefund::query()->whereBelongsTo($locked, 'transaction')->sum('amount_minor');
            if ($alreadyRequested + $amountMinor > $locked->amount_minor) {
                throw new DomainException('Refund requests exceed the reconciled transaction amount.');
            }
            $refund = new PaymentRefund;
            $refund->forceFill(['payment_transaction_id' => $locked->getKey(), 'requested_by_user_id' => $actor?->getKey(), 'amount_minor' => $amountMinor, 'currency' => $locked->currency, 'reason_code' => $reasonCode, 'status' => PaymentRefundStatus::Requested, 'idempotency_scope_hash' => $scopeHash, 'payload_fingerprint' => $fingerprint, 'requested_at' => now()->utc()])->save();
            $this->recordAuditEvent->handle(new AuditEventData(action: 'finance.refund.requested', actor: $actor, targetType: 'payment_refund', targetId: $refund->public_id, metadata: ['amount_minor' => $amountMinor, 'currency' => $locked->currency, 'reason_code' => $reasonCode]));

            return $refund;
        }, attempts: 3);
    }
}
