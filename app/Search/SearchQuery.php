<?php

namespace App\Search;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class SearchQuery
{
    /**
     * @param  list<string>  $resourceTypes
     * @param  array<string, string>  $scope
     */
    public function __construct(
        public string $term,
        public array $resourceTypes = [],
        public array $scope = [],
        public int $limit = 20,
    ) {
        $length = Str::length(trim($term));

        if ($length < 2 || $length > 200) {
            throw new InvalidArgumentException('Search terms must contain between 2 and 200 characters.');
        }

        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Search limits must be between 1 and 100.');
        }

        foreach (array_merge($resourceTypes, array_keys($scope)) as $code) {
            if (! is_string($code) || ! Str::isMatch('/\A[a-z][a-z0-9._-]*\z/', $code)) {
                throw new InvalidArgumentException('Search filters require stable lowercase codes.');
            }
        }
    }
}
