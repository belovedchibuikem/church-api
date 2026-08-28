<?php

namespace App\AdvisoryAi;

final readonly class AdvisoryResponse
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $available,
        public ?string $recommendation,
        public string $reasonCode,
        public array $metadata = [],
        public bool $requiresHumanDecision = true,
    ) {}

    public static function unavailable(string $reasonCode = 'provider_disabled'): self
    {
        return new self(false, null, $reasonCode);
    }
}
