<?php

namespace App\Search;

use App\Search\Contracts\SearchProvider;

final class NullSearchProvider implements SearchProvider
{
    public function search(SearchQuery $query): array
    {
        return [];
    }
}
