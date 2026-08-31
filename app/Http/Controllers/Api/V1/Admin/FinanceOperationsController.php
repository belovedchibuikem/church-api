<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Finance\Actions\CreatePaymentIntentAction;
use App\Finance\Actions\IssuePaymentReceiptAction;
use App\Finance\Actions\ReconcilePaymentTransactionAction;
use App\Finance\Actions\RequestPaymentRefundAction;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CreatePaymentIntentRequest;
use App\Http\Requests\Api\V1\Admin\ReconcilePaymentTransactionRequest;
use App\Http\Requests\Api\V1\Admin\RequestPaymentRefundRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Models\EventRegistration;
use App\Models\PaymentIntent;
use App\Models\PaymentReceipt;
use App\Models\PaymentReconciliation;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function showTransaction(Request $request, string $transaction, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = PaymentTransaction::query()
            ->with([
                'intent:id,public_id,purpose_code,status,payer_person_id,currency,amount_minor',
                ...PersonDisplayName::eager('intent.payer'),
                'reconciliation:id,public_id,status,reason_code,reconciled_at,payment_transaction_id',
                'receipt:id,public_id,receipt_number,issued_at,payment_transaction_id',
            ])
            ->where('public_id', $transaction)
            ->firstOrFail();

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($target))->resolve($request));
    }

    public function createIntent(CreatePaymentIntentRequest $request, CreatePaymentIntentAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $registration = EventRegistration::query()->where('public_id', $request->validated('event_registration_id'))->firstOrFail();
        $intent = $this->execute(fn (): PaymentIntent => $action->handle(
            $registration,
            (string) $request->validated('idempotency_key'),
            $context->actor($request),
        ));
        $intent->load(['payer:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($intent))->resolve($request), status: 201);
    }

    public function requestRefund(RequestPaymentRefundRequest $request, string $transaction, RequestPaymentRefundAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = PaymentTransaction::query()->where('public_id', $transaction)->firstOrFail();
        $refund = $this->execute(fn (): PaymentRefund => $action->handle(
            $target,
            (int) $request->validated('amount_minor'),
            (string) $request->validated('reason_code'),
            (string) $request->validated('idempotency_key'),
            $context->actor($request),
        ));
        $refund->load(['transaction:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($refund))->resolve($request), status: 201);
    }

    public function reconcileTransaction(
        ReconcilePaymentTransactionRequest $request,
        string $transaction,
        ReconcilePaymentTransactionAction $action,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $target = PaymentTransaction::query()->where('public_id', $transaction)->firstOrFail();
        $reconciliation = $this->execute(fn (): PaymentReconciliation => $action->handle(
            $target,
            $context->actor($request),
            (string) ($request->validated('reason_code') ?? 'manual_admin_reconcile'),
        ));
        $reconciliation->load([
            'transaction:id,public_id,provider_code,amount_minor,currency,occurred_at,payment_intent_id',
            'transaction.intent:id,public_id,purpose_code,payer_person_id',
            ...PersonDisplayName::eager('transaction.intent.payer'),
        ]);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($reconciliation))->resolve($request), status: 201);
    }

    public function issueReceipt(Request $request, string $transaction, IssuePaymentReceiptAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = PaymentTransaction::query()->where('public_id', $transaction)->firstOrFail();
        $receipt = $this->execute(fn (): PaymentReceipt => $action->handle(
            $target,
            $context->actor($request),
        ));
        $receipt->load([
            'transaction:id,public_id,provider_code,amount_minor,currency,occurred_at,payment_intent_id',
            'transaction.intent:id,public_id,purpose_code,payer_person_id',
            ...PersonDisplayName::eager('transaction.intent.payer'),
        ]);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($receipt))->resolve($request), status: 201);
    }
}
