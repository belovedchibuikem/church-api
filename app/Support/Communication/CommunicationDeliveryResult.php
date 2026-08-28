<?php

namespace App\Support\Communication;

use App\Communication\CommunicationDeliveryStatus;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class CommunicationDeliveryResult
{
    public function __construct(
        public CommunicationDeliveryStatus $status,
        public string $resultCode,
    ) {
        if ($this->status === CommunicationDeliveryStatus::Pending) {
            throw new InvalidArgumentException('Delivery gateways must return a terminal result.');
        }

        if (
            Str::length($this->resultCode) > 100
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $this->resultCode)
        ) {
            throw new InvalidArgumentException('Delivery result codes must be stable lowercase identifiers.');
        }
    }

    public static function providerUnconfigured(): self
    {
        return new self(CommunicationDeliveryStatus::Suppressed, 'provider_unconfigured');
    }
}
