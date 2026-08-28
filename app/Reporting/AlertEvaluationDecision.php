<?php

namespace App\Reporting;

final readonly class AlertEvaluationDecision
{
    public function __construct(
        public bool $allowed,
        public bool $matched,
        public string $reasonCode,
    ) {}

    public static function denied(string $reasonCode = 'alert_policy_pending'): self
    {
        return new self(false, false, $reasonCode);
    }

    public static function noMatch(string $reasonCode = 'condition_not_matched'): self
    {
        return new self(true, false, $reasonCode);
    }

    public static function matched(string $reasonCode = 'condition_matched'): self
    {
        return new self(true, true, $reasonCode);
    }
}
