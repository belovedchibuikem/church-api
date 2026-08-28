<?php

namespace App\Queries\PublicEvents;

use App\Models\MinistryEvent;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListPublicEventsQuery
{
    /**
     * @param  array{
     *     category?: string,
     *     starts_from?: string,
     *     starts_until?: string,
     *     sort?: 'starts_at'|'-starts_at'|'name'|'-name',
     *     page?: int,
     *     per_page?: int
     * }  $filters
     * @return LengthAwarePaginator<int, MinistryEvent>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'starts_at';
        $sortColumn = ltrim($sort, '-');
        $sortDirection = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return MinistryEvent::query()
            ->select([
                'id', 'public_id', 'location_id', 'category_code', 'name', 'starts_at', 'ends_at',
                'fee_amount_minor', 'fee_currency',
            ])
            ->with(['location:id,public_id,name,locality,timezone', 'mediaAttachments.fileAsset'])
            ->publiclyUpcoming()
            ->when(
                $filters['category'] ?? null,
                fn ($query, string $category) => $query->where('category_code', $category),
            )
            ->when(
                $filters['starts_from'] ?? null,
                fn ($query, string $date) => $query->where(
                    'starts_at',
                    '>=',
                    CarbonImmutable::parse($date, 'UTC')->startOfDay(),
                ),
            )
            ->when(
                $filters['starts_until'] ?? null,
                fn ($query, string $date) => $query->where(
                    'starts_at',
                    '<=',
                    CarbonImmutable::parse($date, 'UTC')->endOfDay(),
                ),
            )
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('public_id')
            ->paginate(perPage: $filters['per_page'] ?? 20)
            ->withQueryString();
    }
}
