<?php

namespace App\Support\Church;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class StableReasonCode
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = Str::of($value)->trim()->lower()->toString();

        if (
            Str::length($normalized) > 100
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $normalized)
        ) {
            throw new InvalidArgumentException('Workflow reasons must be stable lowercase identifiers.');
        }

        $this->value = $normalized;
    }
}
