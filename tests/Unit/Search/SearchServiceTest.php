<?php

namespace Tests\Unit\Search;

use App\Search\Contracts\SearchProvider;
use App\Search\NullSearchProvider;
use App\Search\SearchQuery;
use App\Search\SearchResult;
use App\Search\SearchService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SearchServiceTest extends TestCase
{
    public function test_default_provider_has_no_external_side_effects(): void
    {
        $this->assertSame([], (new SearchService(new NullSearchProvider))->search(new SearchQuery('church')));
    }

    public function test_results_outside_the_authorized_classifications_are_removed(): void
    {
        $provider = new class implements SearchProvider
        {
            public function search(SearchQuery $query): array
            {
                return [
                    new SearchResult('church', '1', 'Public church'),
                    new SearchResult('safeguarding_incident', '2', 'Restricted incident', classification: 'restricted'),
                ];
            }
        };

        $results = (new SearchService($provider))->search(new SearchQuery('church'));

        $this->assertCount(1, $results);
        $this->assertSame('church', $results[0]->resourceType);
    }

    public function test_short_queries_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SearchQuery('x');
    }
}
