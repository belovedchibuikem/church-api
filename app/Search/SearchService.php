<?php

namespace App\Search;

use App\Search\Contracts\SearchProvider;
use InvalidArgumentException;

final readonly class SearchService
{
    public function __construct(private SearchProvider $provider) {}

    /**
     * @param  list<string>  $allowedClassifications
     * @return list<SearchResult>
     */
    public function search(SearchQuery $query, array $allowedClassifications = ['public']): array
    {
        $results = $this->provider->search($query);
        $safe = [];

        foreach ($results as $result) {
            if (! $result instanceof SearchResult) {
                throw new InvalidArgumentException('Search providers must return typed search results.');
            }

            if (in_array($result->classification, $allowedClassifications, true)) {
                $safe[] = $result;
            }

            if (count($safe) === $query->limit) {
                break;
            }
        }

        return $safe;
    }
}
