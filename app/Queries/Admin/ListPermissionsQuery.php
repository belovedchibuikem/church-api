<?php

namespace App\Queries\Admin;

use App\Models\Permission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListPermissionsQuery
{
    /** @return LengthAwarePaginator<int, Permission> */
    public function paginate(?string $search, string $sort, int $perPage): LengthAwarePaginator
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return Permission::query()->select(['id', 'public_id', 'code'])
            ->when($search !== null, function (Builder $query) use ($search): void {
                $escaped = addcslashes(trim($search), '\\%_');
                $query->where('code', 'like', "%{$escaped}%");
            })
            ->orderBy('code', $direction)->orderBy('id')->paginate($perPage)->withQueryString();
    }
}
