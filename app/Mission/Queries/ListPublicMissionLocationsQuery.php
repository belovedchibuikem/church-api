<?php

namespace App\Mission\Queries;

use App\Models\Crusade;
use App\Models\Location;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListPublicMissionLocationsQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Location>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $status = (string) ($filters['status'] ?? 'upcoming');

        $query = Location::query()
            ->select(['id', 'public_id', 'country_id', 'name', 'locality', 'timezone', 'latitude', 'longitude'])
            ->with('country:id,iso_code,name')
            ->whereHas('crusades', function (Builder $crusadeQuery) use ($status): void {
                $this->applyCrusadeVisibility($crusadeQuery, $status);
            });

        if (isset($filters['country'])) {
            $countryCode = (string) $filters['country'];
            $query->whereHas('country', fn (Builder $countryQuery): Builder => $countryQuery->where('iso_code', $countryCode));
        }

        if (isset($filters['q'])) {
            $searchTerm = '%'.$this->escapeLike((string) $filters['q']).'%';
            $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                $searchQuery
                    ->where('name', 'like', $searchTerm)
                    ->orWhere('locality', 'like', $searchTerm);
            });
        }

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();
    }

    /** @param Builder<Crusade> $query */
    private function applyCrusadeVisibility(Builder $query, string $status): void
    {
        $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()->utc());

        if ($status === 'upcoming') {
            $query->where(function (Builder $visibilityQuery): void {
                $visibilityQuery->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
        }

        if ($status === 'past') {
            $query->whereNotNull('ends_at')->where('ends_at', '<', now());
        }
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
