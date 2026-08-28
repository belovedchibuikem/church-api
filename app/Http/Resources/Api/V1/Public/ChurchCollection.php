<?php

namespace App\Http\Resources\Api\V1\Public;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ChurchCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'items' => ChurchResource::collection($this->collection)->resolve($request),
        ];
    }

    /** @return array{pagination: array{current_page: int, per_page: int, total: int, last_page: int}} */
    public function paginationMeta(): array
    {
        /** @var LengthAwarePaginator<int, mixed> $paginator */
        $paginator = $this->resource;

        return [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
