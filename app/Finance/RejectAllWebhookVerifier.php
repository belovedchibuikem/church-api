<?php

namespace App\Finance;

use App\Finance\Contracts\WebhookVerifier;
use App\Finance\Data\PaymentWebhookEnvelope;
use App\Finance\Data\VerifiedPaymentWebhook;

class RejectAllWebhookVerifier implements WebhookVerifier
{
    public function verify(PaymentWebhookEnvelope $envelope): ?VerifiedPaymentWebhook
    {
        return null;
    }
}
