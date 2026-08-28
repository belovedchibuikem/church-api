<?php

namespace App\Support\Platform;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class PlatformKey
{
    public function __construct(public string $value)
    {
        if (
            Str::length($this->value) > 191
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $this->value)
            || ! Str::contains($this->value, '.')
        ) {
            throw new InvalidArgumentException('Platform keys must be stable namespaced lowercase identifiers.');
        }
    }
}
