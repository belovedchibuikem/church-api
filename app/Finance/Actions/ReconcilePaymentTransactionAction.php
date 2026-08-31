<?php

namespace App\Finance\Actions;

use App\Finance\PaymentReconciliationStatus;
use App\Models\PaymentReconciliation;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReconcilePaymentTransactionAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(PaymentTransaction $transaction, User $actor, string $reasonCode = 'manual_admin_reconcile'): PaymentReconciliation
    {
        return DB::transaction(function () use ($transaction, $actor, $reasonCode): PaymentReconciliation {
            $locked = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());
            $existing = PaymentReconciliation::query()
                ->where('payment_transaction_id', $locked->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($locked->intent()->lockForUpdate()->first() === null) {
                throw new DomainException('Payment transaction is missing its intent.');
            }

            $reconciliation = new PaymentReconciliation;
            $reconciliation->forceFill([
                'payment_transaction_id' => $locked->getKey(),
                'status' => PaymentReconciliationStatus::Matched,
                'reason_code' => $reasonCode,
                'reconciled_at' => now()->utc(),
                'created_at' => now()->utc(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'finance.payment.reconciled',
                actor: $actor,
                targetType: 'payment_transaction',
                targetId: $locked->public_id,
                metadata: [
                    'status' => PaymentReconciliationStatus::Matched->value,
                    'reason_code' => $reasonCode,
                    'manual' => true,
                ],
            ));

            return $reconciliation;
        }, attempts: 3);
    }
}
