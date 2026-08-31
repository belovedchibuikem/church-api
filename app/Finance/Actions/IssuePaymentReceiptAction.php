<?php

namespace App\Finance\Actions;

use App\Models\PaymentReceipt;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssuePaymentReceiptAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(PaymentTransaction $transaction, User $actor): PaymentReceipt
    {
        return DB::transaction(function () use ($transaction, $actor): PaymentReceipt {
            $locked = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());
            $existing = PaymentReceipt::query()
                ->where('payment_transaction_id', $locked->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $receipt = new PaymentReceipt;
            $receipt->forceFill([
                'payment_transaction_id' => $locked->getKey(),
                'receipt_number' => 'FHC-'.strtoupper(Str::substr((string) $locked->public_id, -10)),
                'issued_at' => now()->utc(),
                'created_at' => now()->utc(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'finance.payment.receipt_issued',
                actor: $actor,
                targetType: 'payment_receipt',
                targetId: $receipt->public_id,
                metadata: [
                    'payment_transaction_id' => $locked->public_id,
                    'manual' => true,
                ],
            ));

            return $receipt;
        }, attempts: 3);
    }
}
