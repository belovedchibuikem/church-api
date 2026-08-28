<?php

namespace App\Reporting;

use App\Support\Authorization\ScopeReference;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

final readonly class AlertEvaluationContext
{
    /**
     * @param  array<string, bool|float|int|string|null>  $facts
     *
     * @throws JsonException
     */
    public function __construct(
        public string $conditionReferenceType,
        public string $conditionReferenceKey,
        public ?ScopeReference $scope = null,
        public ?string $summary = null,
        public array $facts = [],
    ) {
        if (
            Str::length($this->conditionReferenceType) > 100
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $this->conditionReferenceType)
        ) {
            throw new InvalidArgumentException('Alert condition reference types must be stable lowercase identifiers.');
        }

        if (
            Str::length($this->conditionReferenceKey) > 191
            || ! Str::isMatch('/\A[^\s\x00-\x1F\x7F]+\z/u', $this->conditionReferenceKey)
        ) {
            throw new InvalidArgumentException('Alert condition reference keys must be non-empty opaque identifiers.');
        }

        if ($this->summary !== null && Str::length($this->summary) > 1000) {
            throw new InvalidArgumentException('Alert summaries may not exceed 1000 characters.');
        }

        json_encode($this->facts, JSON_THROW_ON_ERROR);
    }
}
