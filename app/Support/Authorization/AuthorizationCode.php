<?php

namespace App\Support\Authorization;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class AuthorizationCode
{
    public function __construct(public string $value)
    {
        if (
            Str::length($this->value) > 191
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $this->value)
        ) {
            throw new InvalidArgumentException('Authorization codes must be stable lowercase identifiers.');
        }
    }
}
