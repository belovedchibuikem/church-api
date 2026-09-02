<?php

namespace App\Queries\Admin;

use App\Models\User;
use App\Support\Authorization\AuthorizationBundleCatalog;
use App\Support\Authorization\ScopeDatabaseFilter;
use App\Support\Authorization\ScopeReference;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListScopedUsersQuery
{
    public function __construct(
        private readonly ScopeDatabaseFilter $scopeFilter,
    ) {}

    /**
     * @param  array{search?: string, status?: string, email_verified?: bool, exclude_app_members?: bool}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(User $actor, ScopeReference $scope, array $filters, string $sort, int $perPage): LengthAwarePaginator
    {
        $now = now()->utc();

        return $this->baseQuery($actor, $scope)
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $search = addcslashes(trim($filters['search']), '\\%_');
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['status']), fn (Builder $query): Builder => $query->where('account_status', $filters['status']))
            ->when(array_key_exists('email_verified', $filters), function (Builder $query) use ($filters): void {
                $filters['email_verified'] ? $query->whereNotNull('email_verified_at') : $query->whereNull('email_verified_at');
            })
            ->when(! empty($filters['exclude_app_members']), function (Builder $query) use ($now): void {
                $memberRoleCode = AuthorizationBundleCatalog::MEMBER_SECURITY_ROLE;
                $query->where(function (Builder $visibleQuery) use ($memberRoleCode, $now): void {
                    $visibleQuery
                        ->whereDoesntHave('roleAssignments', fn (Builder $assignmentQuery): Builder => $assignmentQuery->active($now))
                        ->orWhereHas('roleAssignments', function (Builder $assignmentQuery) use ($memberRoleCode, $now): void {
                            $assignmentQuery
                                ->active($now)
                                ->whereHas('role', fn (Builder $roleQuery): Builder => $roleQuery->where('code', '!=', $memberRoleCode));
                        });
                });
            })
            ->tap(fn (Builder $query): Builder => $this->applySort($query, $sort))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(User $actor, ScopeReference $scope, string $publicId): User
    {
        return $this->baseQuery($actor, $scope)->where('public_id', $publicId)->firstOrFail();
    }

    private function baseQuery(User $actor, ScopeReference $scope): Builder
    {
        $now = now()->utc();
        $query = User::query()
            ->select(['id', 'public_id', 'person_id', 'name', 'email', 'email_verified_at', 'account_status', 'suspension_reason', 'suspended_at', 'reactivated_at', 'created_at'])
            ->with([
                'person:id,public_id',
                'person.profile:id,person_id,given_name,middle_name,family_name,preferred_name',
                'roleAssignments' => function (HasMany $roleQuery) use ($now): void {
                    $roleQuery
                        ->select(['id', 'public_id', 'user_id', 'role_id', 'assigned_at', 'expires_at'])
                        ->active($now)
                        ->with(['role:id,public_id,code,name', 'scopeAssignments:id,public_id,role_assignment_id,scope_type,scope_key,created_at']);
                },
            ]);

        if ($scope->type === 'global' && $scope->key === 'platform') {
            return $query;
        }

        if ($scope->type === 'own_record') {
            $ownsRequestedKey = $scope->key === $actor->public_id
                || ($actor->person_id !== null && $actor->person()->where('public_id', $scope->key)->exists());

            return $ownsRequestedKey ? $query->whereKey($actor->getKey()) : $query->whereRaw('1 = 0');
        }

        return $query->whereHas('roleAssignments', function (Builder $assignmentQuery) use ($scope, $now): void {
            $assignmentQuery->active($now)->whereHas(
                'scopeAssignments',
                fn (Builder $scopeQuery): Builder => $this->scopeFilter->apply($scopeQuery, $scope),
            );
        });
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return $query->orderBy(ltrim($sort, '-'), $direction)->orderBy('id');
    }
}
