<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Exceptions\PaymentReconciliationMismatchException;
use App\Exceptions\PaymentVerificationException;
use App\Finance\Actions\ReconcilePaymentWebhookAction;
use App\Finance\Actions\RecordPaymentDisputeAction;
use App\Finance\Data\PaymentWebhookEnvelope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\PaymentWebhookRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Models\PaymentDispute;
use App\Models\PaymentReconciliation;
use App\Support\Api\ApiResponse;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PublicPaymentWebhookController extends Controller
{
    public function reconcile(PaymentWebhookRequest $request, ReconcilePaymentWebhookAction $action): JsonResponse
    {
        $result = $this->execute(fn (): PaymentReconciliation => $action->handle($this->envelope($request)));
        $result->load(['transaction:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($result))->resolve($request), status: 201);
    }

    public function dispute(PaymentWebhookRequest $request, RecordPaymentDisputeAction $action): JsonResponse
    {
        $result = $this->execute(fn (): PaymentDispute => $action->handle($this->envelope($request)));
        $result->load(['transaction:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($result))->resolve($request), status: 201);
    }

    private function envelope(PaymentWebhookRequest $request): PaymentWebhookEnvelope
    {
        return new PaymentWebhookEnvelope(
            providerCode: (string) $request->validated('provider_code'),
            eventId: (string) $request->validated('event_id'),
            signature: $request->header('X-Payment-Signature'),
            payload: (array) $request->validated('payload'),
            receivedAt: new DateTimeImmutable('now'),
        );
    }

    private function execute(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (
            InvalidArgumentException|
            LogicException|
            DomainException|
            PaymentVerificationException|
            PaymentReconciliationMismatchException $exception
        ) {
            throw new UnprocessableEntityHttpException(previous: $exception);
        }
    }
}
