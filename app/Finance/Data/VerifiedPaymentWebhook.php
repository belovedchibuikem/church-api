<?php

namespace App\Finance\Data;

use App\Finance\PaymentDisputeStatus;
use DateTimeImmutable;

final readonly class VerifiedPaymentWebhook
{
    public function __construct(
        public string $type,
        public string $providerCode,
        public string $eventId,
        public string $paymentIntentPublicId,
        public string $providerReference,
        public int $amountMinor,
        public string $currency,
        public DateTimeImmutable $occurredAt,
        public ?string $reasonCode = null,
        public ?string $disputeCaseId = null,
        public ?PaymentDisputeStatus $disputeStatus = null,
    ) {}
}
