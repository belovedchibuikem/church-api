<?php

namespace App\Search;

final readonly class SearchResult
{
    /** @param array<string, scalar|null> $metadata */
    public function __construct(
        public string $resourceType,
        public string $resourceId,
        public string $title,
        public ?string $summary = null,
        public array $metadata = [],
        public string $classification = 'public',
    ) {}
}
