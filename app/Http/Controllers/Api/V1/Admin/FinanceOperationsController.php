<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Finance\Actions\CreatePaymentIntentAction;
use App\Finance\Actions\RequestPaymentRefundAction;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CreatePaymentIntentRequest;
use App\Http\Requests\Api\V1\Admin\RequestPaymentRefundRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Models\EventRegistration;
use App\Models\PaymentIntent;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class FinanceOperationsController extends Controller
{
    use ExecutesDomainMutations;

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
}
