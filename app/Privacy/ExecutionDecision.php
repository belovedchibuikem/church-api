<?php

namespace App\Privacy;

final readonly class ExecutionDecision
{
    public function __construct(public bool $allowed, public string $reasonCode) {}

    public static function allowed(string $reasonCode = 'export_execution_allowed'): self
    {
        return new self(true, $reasonCode);
    }

    public static function denied(string $reasonCode = 'retention_policy_pending'): self
    {
        return new self(false, $reasonCode);
    }
}
