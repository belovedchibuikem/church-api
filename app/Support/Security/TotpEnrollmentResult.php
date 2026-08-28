<?php

namespace App\Support\Security;

use App\Models\MfaMethod;

final readonly class TotpEnrollmentResult
{
    /** @param array<int, string> $recoveryCodes */
    public function __construct(
        public MfaMethod $method,
        public string $secret,
        public string $provisioningUri,
        public array $recoveryCodes,
    ) {}
}
