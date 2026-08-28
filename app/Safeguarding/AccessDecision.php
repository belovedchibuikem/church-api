<?php

namespace App\Safeguarding;

final readonly class AccessDecision
{
    public function __construct(
        public bool $allowed,
        public string $reasonCode,
    ) {}

    public static function denied(string $reasonCode = 'restricted_policy_pending'): self
    {
        return new self(false, $reasonCode);
    }
}
