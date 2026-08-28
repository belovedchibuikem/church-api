<?php

namespace App\Support\Authorization;

use App\Models\ScopeAssignment;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class ScopeReference
{
    public function __construct(
        public string $type,
        public string $key,
    ) {
        if (
            Str::length($this->type) > 100
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $this->type)
        ) {
            throw new InvalidArgumentException('Scope types must be stable lowercase identifiers.');
        }

        if (
            Str::length($this->key) > 64
            || ! Str::isMatch('/\A[^\s\x00-\x1F\x7F]+\z/u', $this->key)
        ) {
            throw new InvalidArgumentException('Scope keys must be non-empty opaque reference identifiers.');
        }
    }

    public static function fromAssignment(ScopeAssignment $assignment): self
    {
        return new self($assignment->scope_type, $assignment->scope_key);
    }
}
