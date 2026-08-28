<?php

namespace App\Queries\Admin;

use App\Models\ScopeAssignment;
use App\Support\Authorization\ScopeDatabaseFilter;
use App\Support\Authorization\ScopeReference;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListScopeAssignmentsQuery
{
    public function __construct(private readonly ScopeDatabaseFilter $scopeFilter) {}

    /**
     * @param  array{scope_type?: string, role_code?: string, user?: string}  $filters
     * @return LengthAwarePaginator<int, ScopeAssignment>
     */
    public function paginate(ScopeReference $scope, array $filters, string $sort, int $perPage): LengthAwarePaginator
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        return $this->scopeFilter->apply(ScopeAssignment::query(), $scope)
            ->select(['id', 'public_id', 'role_assignment_id', 'scope_type', 'scope_key', 'created_at'])
            ->with([
                'roleAssignment:id,public_id,user_id,role_id,assigned_at,expires_at',
                'roleAssignment.role:id,public_id,code,name',
                'roleAssignment.user:id,public_id,name,email',
            ])
            ->when(isset($filters['scope_type']), fn (Builder $query): Builder => $query->where('scope_type', $filters['scope_type']))
            ->when(isset($filters['role_code']), fn (Builder $query): Builder => $query
                ->whereHas('roleAssignment.role', fn (Builder $roleQuery): Builder => $roleQuery->where('code', $filters['role_code'])))
            ->when(isset($filters['user']), fn (Builder $query): Builder => $query
                ->whereHas('roleAssignment.user', fn (Builder $userQuery): Builder => $userQuery->where('public_id', $filters['user'])))
            ->orderBy($column, $direction)->orderBy('id')->paginate($perPage)->withQueryString();
    }
}
