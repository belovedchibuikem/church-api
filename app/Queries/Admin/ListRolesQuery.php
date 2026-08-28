<?php

namespace App\Queries\Admin;

use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListRolesQuery
{
    /** @return LengthAwarePaginator<int, Role> */
    public function paginate(?string $search, string $sort, int $perPage): LengthAwarePaginator
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        return Role::query()->select(['id', 'public_id', 'code', 'name'])
            ->with(['rolePermissions' => function (HasMany $query): void {
                $query
                    ->select(['id', 'role_id', 'permission_id'])
                    ->with('permission:id,public_id,code');
            }])
            ->when($search !== null, function (Builder $query) use ($search): void {
                $escaped = addcslashes(trim($search), '\\%_');
                $query->where(fn (Builder $searchQuery): Builder => $searchQuery
                    ->where('code', 'like', "%{$escaped}%")->orWhere('name', 'like', "%{$escaped}%"));
            })
            ->orderBy($column, $direction)->orderBy('id')->paginate($perPage)->withQueryString();
    }
}
