<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Exceptions\PaymentGatewayException;
use App\Exceptions\PaymentGovernanceDeniedException;
use App\Finance\Actions\CompleteLocalGivingAction;
use App\Finance\Actions\CreateGivingIntentAction;
use App\Finance\Actions\CreatePaymentIntentAction;
use App\Finance\Contracts\PaymentGateway;
use App\Finance\PaymentIntentStatus;
use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\CompleteGivingIntentRequest;
use App\Http\Requests\Api\V1\User\CreateEventPaymentIntentRequest;
use App\Http\Requests\Api\V1\User\CreateGivingIntentRequest;
use App\Http\Resources\Api\V1\User\UserPaymentIntentResource;
use App\Http\Resources\Api\V1\User\UserPaymentReceiptResource;
use App\Http\Resources\Api\V1\User\UserPaymentTransactionResource;
use App\Models\EventRegistration;
use App\Models\FileAsset;
use App\Models\PaymentIntent;
use App\Models\PaymentReceipt;
use App\Models\PaymentTransaction;
use App\Support\Api\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;

class PaymentController extends Controller
{
    use ResolvesAuthenticatedPerson;

    public function intents(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $intents = PaymentIntent::query()
            ->with('proofFileAsset:id,public_id')
            ->where('payer_person_id', $person->getKey())
            ->latest('created_at')
            ->limit(100)
            ->get();

        return ApiResponse::success(
            $request,
            UserPaymentIntentResource::collection($intents)->resolve($request),
        );
    }

    public function transactions(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $transactions = PaymentTransaction::query()
            ->with([
                'intent:id,public_id,payer_person_id,purpose_code,status',
                'receipt:id,public_id,payment_transaction_id',
            ])
            ->whereHas('intent', fn ($query) => $query->where('payer_person_id', $person->getKey()))
            ->latest('created_at')
            ->limit(100)
            ->get();

        return ApiResponse::success(
            $request,
            UserPaymentTransactionResource::collection($transactions)->resolve($request),
        );
    }

    public function showIntent(Request $request, string $intent): JsonResponse
    {
        $person = $this->person($request);
        $owned = PaymentIntent::query()
            ->with(['transactions.receipt', 'proofFileAsset:id,public_id'])
            ->where('public_id', $intent)
            ->where('payer_person_id', $person->getKey())
            ->firstOrFail();

        return ApiResponse::success(
            $request,
            UserPaymentIntentResource::make($owned)->resolve($request),
        );
    }

    public function receipt(Request $request, string $receipt): JsonResponse
    {
        $person = $this->person($request);
        $owned = PaymentReceipt::query()
            ->with(['transaction.intent'])
            ->where('public_id', $receipt)
            ->whereHas('transaction.intent', fn ($query) => $query->where('payer_person_id', $person->getKey()))
            ->firstOrFail();

        return ApiResponse::success(
            $request,
            UserPaymentReceiptResource::make($owned)->resolve($request),
        );
    }

    public function storeGivingIntent(
        CreateGivingIntentRequest $request,
        CreateGivingIntentAction $action,
    ): JsonResponse {
        $person = $this->person($request);
        $user = $this->actor($request);
        $validated = $request->validated();

        try {
            $proofId = $validated['proof_file_asset_id'] ?? null;
            $proof = is_string($proofId)
                ? FileAsset::query()->where('public_id', $proofId)->firstOrFail()
                : null;
            $result = $action->handle(
                payer: $person,
                amountMinor: (int) $validated['amount_minor'],
                currency: (string) $validated['currency'],
                idempotencyKey: (string) $validated['idempotency_key'],
                purposeCode: (string) $validated['purpose_code'],
                actor: $user,
                proof: $proof,
            );
        } catch (PaymentGovernanceDeniedException $exception) {
            return ApiResponse::error(
                $request,
                'PAYMENT_GOVERNANCE_DENIED',
                $exception->getMessage(),
                status: 422,
            );
        } catch (PaymentGatewayException $exception) {
            return ApiResponse::error(
                $request,
                $exception->failureCode,
                $exception->getMessage(),
                status: 503,
            );
        } catch (InvalidArgumentException|LogicException|DomainException $exception) {
            return ApiResponse::error(
                $request,
                'VALIDATION_FAILED',
                $exception->getMessage(),
                status: 422,
            );
        }

        $payload = (new UserPaymentIntentResource($result['intent']->loadMissing('proofFileAsset')))
            ->withCheckout($result['client_payload'], $result['provider_code'])
            ->resolve($request);

        return ApiResponse::success($request, $payload, status: 201);
    }

    public function storeEventPaymentIntent(
        CreateEventPaymentIntentRequest $request,
        string $registration,
        CreatePaymentIntentAction $action,
        PaymentGateway $gateway,
    ): JsonResponse {
        $person = $this->person($request);
        $user = $this->actor($request);
        $owned = EventRegistration::query()
            ->where('public_id', $registration)
            ->where('person_id', $person->getKey())
            ->firstOrFail();

        try {
            $intent = $action->handle(
                $owned,
                (string) $request->validated('idempotency_key'),
                $user,
            );

            if ($intent->status === PaymentIntentStatus::Succeeded) {
                $payload = UserPaymentIntentResource::make(
                    $intent->load(['transactions.receipt']),
                )->resolve($request);

                return ApiResponse::success($request, $payload);
            }

            $initiated = $gateway->initiate($intent);
            $payload = (new UserPaymentIntentResource($intent))
                ->withCheckout($initiated['client_payload'], $gateway->providerCode())
                ->resolve($request);
        } catch (PaymentGovernanceDeniedException $exception) {
            return ApiResponse::error(
                $request,
                'PAYMENT_GOVERNANCE_DENIED',
                $exception->getMessage(),
                status: 422,
            );
        } catch (PaymentGatewayException $exception) {
            return ApiResponse::error(
                $request,
                $exception->failureCode,
                $exception->getMessage(),
                status: 503,
            );
        } catch (InvalidArgumentException|LogicException|DomainException $exception) {
            return ApiResponse::error(
                $request,
                'VALIDATION_FAILED',
                $exception->getMessage(),
                status: 422,
            );
        }

        return ApiResponse::success($request, $payload, status: 201);
    }

    public function completeGivingIntent(
        CompleteGivingIntentRequest $request,
        string $intent,
        CompleteLocalGivingAction $action,
    ): JsonResponse {
        $person = $this->person($request);
        $user = $this->actor($request);
        $owned = PaymentIntent::query()
            ->where('public_id', $intent)
            ->where('payer_person_id', $person->getKey())
            ->firstOrFail();
        $proof = FileAsset::query()
            ->where('public_id', $request->validated('proof_file_asset_id'))
            ->firstOrFail();

        try {
            $result = $action->handle($owned, $person, $user, $proof);
        } catch (PaymentGovernanceDeniedException $exception) {
            return ApiResponse::error(
                $request,
                'PAYMENT_GOVERNANCE_DENIED',
                $exception->getMessage(),
                status: 422,
            );
        } catch (InvalidArgumentException|LogicException|DomainException $exception) {
            return ApiResponse::error(
                $request,
                'VALIDATION_FAILED',
                $exception->getMessage(),
                status: 422,
            );
        }

        $result['transaction']->setRelation('intent', $result['intent']);
        $result['receipt']->setRelation('transaction', $result['transaction']);

        return ApiResponse::success($request, [
            'intent' => UserPaymentIntentResource::make($result['intent'])->resolve($request),
            'transaction' => UserPaymentTransactionResource::make($result['transaction'])->resolve($request),
            'receipt' => UserPaymentReceiptResource::make($result['receipt'])->resolve($request),
        ]);
    }
}
