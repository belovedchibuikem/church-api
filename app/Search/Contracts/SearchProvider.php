<?php

namespace App\Search\Contracts;

use App\Search\SearchQuery;
use App\Search\SearchResult;

interface SearchProvider
{
    /** @return list<SearchResult> */
    public function search(SearchQuery $query): array;
}
