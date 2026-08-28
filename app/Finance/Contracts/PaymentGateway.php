<?php

namespace App\Finance\Contracts;

use App\Models\PaymentIntent;

interface PaymentGateway
{
    public function providerCode(): string;

    /** @return array{provider_reference: string, client_payload: array<string, mixed>} */
    public function initiate(PaymentIntent $paymentIntent): array;
}
