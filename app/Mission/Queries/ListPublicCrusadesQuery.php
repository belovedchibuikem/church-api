<?php

namespace App\Mission\Queries;

use App\Models\Crusade;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListPublicCrusadesQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Crusade>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = Crusade::query()
            ->select(['id', 'public_id', 'name', 'location_id', 'starts_at', 'ends_at'])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()->utc())
            ->with([
                'location:id,public_id,country_id,name,locality,timezone,latitude,longitude',
                'location.country:id,iso_code,name',
                'mediaAttachments.fileAsset',
            ]);

        $this->applyVisibility($query, (string) ($filters['status'] ?? 'upcoming'));

        if (isset($filters['country'])) {
            $countryCode = (string) $filters['country'];
            $query->whereHas('location.country', fn (Builder $countryQuery): Builder => $countryQuery->where('iso_code', $countryCode));
        }

        if (isset($filters['q'])) {
            $searchTerm = '%'.$this->escapeLike((string) $filters['q']).'%';
            $query->where('name', 'like', $searchTerm);
        }

        if (isset($filters['starts_from'])) {
            $query->where('starts_at', '>=', CarbonImmutable::createFromFormat('Y-m-d', (string) $filters['starts_from'], 'UTC')->startOfDay());
        }

        if (isset($filters['starts_until'])) {
            $query->where('starts_at', '<=', CarbonImmutable::createFromFormat('Y-m-d', (string) $filters['starts_until'], 'UTC')->endOfDay());
        }

        [$sortColumn, $sortDirection] = match ((string) ($filters['sort'] ?? 'starts_at')) {
            '-starts_at' => ['starts_at', 'desc'],
            'name' => ['name', 'asc'],
            '-name' => ['name', 'desc'],
            default => ['starts_at', 'asc'],
        };

        return $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();
    }

    /** @param Builder<Crusade> $query */
    private function applyVisibility(Builder $query, string $status): void
    {
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
