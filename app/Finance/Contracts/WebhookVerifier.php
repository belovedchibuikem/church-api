<?php

namespace App\Finance\Contracts;

use App\Finance\Data\PaymentWebhookEnvelope;
use App\Finance\Data\VerifiedPaymentWebhook;

interface WebhookVerifier
{
    public function verify(PaymentWebhookEnvelope $envelope): ?VerifiedPaymentWebhook;
}
