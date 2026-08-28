<?php

namespace App\Support\Kca;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class KcaCertificationEligibilityDecision
{
    /**
     * @param  list<string>  $unmetRequirements
     */
    public function __construct(
        public bool $eligible,
        public string $reasonCode,
        public array $unmetRequirements = [],
    ) {
        if (! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $this->reasonCode)) {
            throw new InvalidArgumentException('Certification decisions require a stable reason code.');
        }

        foreach ($this->unmetRequirements as $requirement) {
            if (
                ! is_string($requirement)
                || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $requirement)
            ) {
                throw new InvalidArgumentException('Certification requirements require stable identifiers.');
            }
        }

        if ($this->eligible && $this->unmetRequirements !== []) {
            throw new InvalidArgumentException('Eligible certification decisions cannot contain unmet requirements.');
        }
    }

    public static function policyPending(): self
    {
        return new self(
            eligible: false,
            reasonCode: 'policy_pending',
            unmetRequirements: ['kca_governance_policy'],
        );
    }

    public static function approved(string $reasonCode = 'approved'): self
    {
        return new self(eligible: true, reasonCode: $reasonCode);
    }
}
