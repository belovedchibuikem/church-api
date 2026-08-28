<?php

namespace App\Support\Communication;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class CommunicationPurpose
{
    public function __construct(public string $value)
    {
        if (
            Str::length($this->value) > 100
            || ! Str::isMatch('/\Acommunications\.[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $this->value)
        ) {
            throw new InvalidArgumentException('Communication purposes must be stable namespaced identifiers.');
        }
    }
}
