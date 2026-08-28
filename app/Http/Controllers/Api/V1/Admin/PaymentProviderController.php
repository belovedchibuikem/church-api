<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Finance\Actions\ActivatePaymentProviderAction;
use App\Finance\Actions\ConfigurePaymentProviderAction;
use App\Finance\Actions\DeactivatePaymentProviderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ConfigurePaymentProviderRequest;
use App\Http\Requests\Api\V1\Admin\PaymentProviderActionRequest;
use App\Http\Resources\Api\V1\Admin\PaymentProviderConfigurationResource;
use App\Models\PaymentProviderConfiguration;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class PaymentProviderController extends Controller
{
    public function show(PaymentProviderActionRequest $request, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $configuration = PaymentProviderConfiguration::query()->first();

        if ($configuration === null) {
            return ApiResponse::success($request, [
                'configured' => false,
                'active' => false,
                'active_provider' => null,
                'providers' => [
                    'paystack' => ['label' => 'Paystack', 'credentials_configured' => false],
                    'flutterwave' => ['label' => 'Flutterwave', 'credentials_configured' => false],
                    'stripe' => ['label' => 'Stripe', 'credentials_configured' => false],
                ],
                'allowed_purpose_codes' => ['giving', 'event_payment'],
                'allowed_currencies' => ['NGN'],
            ]);
        }

        return ApiResponse::success($request, (new PaymentProviderConfigurationResource($configuration))->resolve($request));
    }

    public function configure(
        ConfigurePaymentProviderRequest $request,
        ConfigurePaymentProviderAction $configure,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $configuration = $configure->handle($request->validated(), $context->actor($request));

        return ApiResponse::success($request, (new PaymentProviderConfigurationResource($configuration))->resolve($request));
    }

    public function activate(
        PaymentProviderActionRequest $request,
        ActivatePaymentProviderAction $activate,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);

        try {
            $configuration = $activate->handle(
                PaymentProviderConfiguration::query()->firstOrFail(),
                $context->actor($request),
            );
        } catch (RuntimeException $exception) {
            return ApiResponse::error(
                $request,
                $exception->getMessage(),
                'The selected payment provider cannot be activated until its credentials are configured.',
                status: 422,
            );
        }

        return ApiResponse::success($request, (new PaymentProviderConfigurationResource($configuration))->resolve($request));
    }

    public function deactivate(
        PaymentProviderActionRequest $request,
        DeactivatePaymentProviderAction $deactivate,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $deactivate->handle($context->actor($request));

        return ApiResponse::success($request, ['active' => false]);
    }
}
