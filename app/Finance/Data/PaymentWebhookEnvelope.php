<?php

namespace App\Finance\Data;

use DateTimeImmutable;

final readonly class PaymentWebhookEnvelope
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $providerCode,
        public string $eventId,
        public ?string $signature,
        public array $payload,
        public DateTimeImmutable $receivedAt,
    ) {}
}
