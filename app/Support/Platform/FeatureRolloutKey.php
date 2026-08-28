<?php

namespace App\Support\Platform;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class FeatureRolloutKey
{
    public function __construct(public string $value)
    {
        if (
            ! Str::isUlid($this->value)
            && ! Str::isUuid($this->value)
            && ! Str::isMatch('/\A[0-9a-f]{64}\z/', $this->value)
        ) {
            throw new InvalidArgumentException(
                'Feature rollout keys must be opaque ULIDs, UUIDs, or SHA-256 identifiers.',
            );
        }
    }
}
