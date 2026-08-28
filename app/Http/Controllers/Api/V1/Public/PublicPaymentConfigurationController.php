<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Finance\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Models\PaymentProviderConfiguration;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicPaymentConfigurationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configuration = PaymentProviderConfiguration::query()->where('is_active', true)->first();

        if ($configuration === null || ! $configuration->credentialsConfigured()) {
            return ApiResponse::success($request, [
                'active' => false,
                'provider' => null,
                'public_key' => null,
                'checkout_mode' => null,
                'allowed_purpose_codes' => ['giving', 'event_payment'],
                'allowed_currencies' => ['NGN'],
            ]);
        }

        $publicKey = match ($configuration->active_provider) {
            PaymentProvider::Paystack => $configuration->paystack_public_key,
            PaymentProvider::Flutterwave => $configuration->flutterwave_public_key,
            PaymentProvider::Stripe => $configuration->stripe_publishable_key,
        };

        return ApiResponse::success($request, [
            'active' => true,
            'provider' => $configuration->active_provider->value,
            'public_key' => $publicKey,
            'checkout_mode' => 'redirect',
            'allowed_purpose_codes' => $configuration->allowed_purpose_codes,
            'allowed_currencies' => $configuration->allowed_currencies,
        ]);
    }
}
