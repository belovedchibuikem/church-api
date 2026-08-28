<?php

namespace App\Finance;

use App\Finance\Contracts\PaymentGateway;
use App\Models\PaymentIntent;

class NullPaymentGateway implements PaymentGateway
{
    public function providerCode(): string
    {
        return 'none';
    }

    public function initiate(PaymentIntent $paymentIntent): array
    {
        return [
            'provider_reference' => 'none_'.$paymentIntent->public_id,
            'client_payload' => [
                'provider' => 'none',
                'checkout_mode' => 'unavailable',
                'instructions' => 'No payment gateway is configured. Intent remains pending_provider.',
                'intent_id' => $paymentIntent->public_id,
            ],
        ];
    }
}
