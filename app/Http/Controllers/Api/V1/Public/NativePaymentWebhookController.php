<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Exceptions\PaymentReconciliationMismatchException;
use App\Exceptions\PaymentVerificationException;
use App\Finance\Actions\ReconcilePaymentWebhookAction;
use App\Finance\Data\PaymentWebhookEnvelope;
use App\Finance\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class NativePaymentWebhookController extends Controller
{
    public function paystack(Request $request, ReconcilePaymentWebhookAction $action): JsonResponse
    {
        return $this->reconcile($request, $action, PaymentProvider::Paystack, 'X-Paystack-Signature');
    }

    public function flutterwave(Request $request, ReconcilePaymentWebhookAction $action): JsonResponse
    {
        return $this->reconcile($request, $action, PaymentProvider::Flutterwave, 'verif-hash');
    }

    public function stripe(Request $request, ReconcilePaymentWebhookAction $action): JsonResponse
    {
        return $this->reconcile($request, $action, PaymentProvider::Stripe, 'Stripe-Signature');
    }

    private function reconcile(
        Request $request,
        ReconcilePaymentWebhookAction $action,
        PaymentProvider $provider,
        string $signatureHeader,
    ): JsonResponse {
        $raw = $request->getContent();
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            throw new UnprocessableEntityHttpException('The webhook payload must be JSON.');
        }

        $payload['_raw'] = $raw;
        $eventId = (string) ($payload['id'] ?? data_get($payload, 'data.id') ?? $provider->value.'-'.hash('sha256', $raw));

        try {
            $result = $action->handle(new PaymentWebhookEnvelope(
                providerCode: $provider->value,
                eventId: $eventId,
                signature: $request->header($signatureHeader),
                payload: $payload,
                receivedAt: new DateTimeImmutable('now'),
            ));
        } catch (
            InvalidArgumentException|
            LogicException|
            DomainException|
            PaymentVerificationException|
            PaymentReconciliationMismatchException $exception
        ) {
            throw new UnprocessableEntityHttpException(previous: $exception);
        }

        $result->load(['transaction:id,public_id']);

        return ApiResponse::success($request, [
            'id' => $result->public_id,
            'status' => $result->status instanceof \BackedEnum ? $result->status->value : $result->status,
        ], status: 201);
    }
}
