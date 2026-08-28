<?php

namespace App\Support\Authorization;

use App\Models\AccessDecision;

final readonly class AccessDecisionResult
{
    public function __construct(
        public bool $allowed,
        public AccessDecisionReason $reason,
        public AccessDecision $record,
    ) {}
}
