<?php

namespace App\Support\Communication;

final readonly class GuardianCommunicationDecision
{
    public function __construct(public bool $allowed, public string $reasonCode) {}

    public static function allow(): self
    {
        return new self(true, 'guardian_policy_allows');
    }

    public static function deny(string $reasonCode): self
    {
        return new self(false, $reasonCode);
    }
}
