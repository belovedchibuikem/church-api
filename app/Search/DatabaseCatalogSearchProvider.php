<?php

namespace App\Search;

use App\Models\Church;
use App\Models\ContentPage;
use App\Models\Crusade;
use App\Models\MinistryEvent;
use App\Models\PressPublication;
use App\Press\PressPublicationStatus;
use App\Search\Contracts\SearchProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class DatabaseCatalogSearchProvider implements SearchProvider
{
    public function search(SearchQuery $query): array
    {
        $pattern = '%'.$this->escapeLike(trim($query->term)).'%';
        $cap = max(1, $query->limit * 2);
        $results = [];

        if ($this->includes($query, 'church')) {
            $this->append(
                $results,
                $cap,
                Church::query()->where('name', 'like', $pattern)->latest()->limit($cap)->get(),
                'church',
                static fn (Church $record): array => [
                    'title' => $record->name,
                    'summary' => null,
                    'metadata' => [],
                ],
            );
        }

        if ($this->includes($query, 'ministry_event')) {
            $this->append(
                $results,
                $cap,
                MinistryEvent::query()
                    ->whereNotNull('published_at')
                    ->where('name', 'like', $pattern)
                    ->latest()
                    ->limit($cap)
                    ->get(),
                'ministry_event',
                static fn (MinistryEvent $record): array => [
                    'title' => $record->name,
                    'summary' => $record->category_code,
                    'metadata' => ['category_code' => $record->category_code],
                ],
            );
        }

        if ($this->includes($query, 'press_publication')) {
            $this->append(
                $results,
                $cap,
                PressPublication::query()
                    ->where(function (Builder $publicationQuery) {
                        $publicationQuery
                            ->where('status', PressPublicationStatus::Published)
                            ->orWhereNotNull('published_at');
                    })
                    ->where('title', 'like', $pattern)
                    ->latest()
                    ->limit($cap)
                    ->get(),
                'press_publication',
                static fn (PressPublication $record): array => [
                    'title' => $record->title,
                    'summary' => $record->subtitle,
                    'metadata' => ['language_code' => $record->language_code],
                ],
            );
        }

        if ($this->includes($query, 'mission_crusade')) {
            $this->append(
                $results,
                $cap,
                Crusade::query()
                    ->whereNotNull('published_at')
                    ->where('name', 'like', $pattern)
                    ->latest()
                    ->limit($cap)
                    ->get(),
                'mission_crusade',
                static fn (Crusade $record): array => [
                    'title' => $record->name,
                    'summary' => null,
                    'metadata' => [],
                ],
            );
        }

        if ($this->includes($query, 'content_page') && Schema::hasTable('content_pages')) {
            $this->append(
                $results,
                $cap,
                ContentPage::query()
                    ->whereNotNull('published_at')
                    ->where(function (Builder $pageQuery) use ($pattern): void {
                        $pageQuery
                            ->where('title', 'like', $pattern)
                            ->orWhere('slug', 'like', $pattern);
                    })
                    ->latest()
                    ->limit($cap)
                    ->get(),
                'content_page',
                static fn (ContentPage $record): array => [
                    'title' => $record->title,
                    'summary' => $record->slug,
                    'metadata' => ['slug' => $record->slug],
                ],
            );
        }

        return array_slice($results, 0, $cap);
    }

    private function includes(SearchQuery $query, string $resourceType): bool
    {
        return $query->resourceTypes === [] || in_array($resourceType, $query->resourceTypes, true);
    }

    /**
     * @param  list<SearchResult>  $results
     * @param  callable(Model): array{title: string, summary: ?string, metadata: array<string, scalar|null>}  $mapper
     */
    private function append(array &$results, int $cap, iterable $records, string $resourceType, callable $mapper): void
    {
        foreach ($records as $record) {
            if (count($results) >= $cap) {
                return;
            }

            $mapped = $mapper($record);
            $results[] = new SearchResult(
                resourceType: $resourceType,
                resourceId: $record->public_id,
                title: $mapped['title'],
                summary: $mapped['summary'],
                metadata: $mapped['metadata'],
                classification: 'public',
            );
        }
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
