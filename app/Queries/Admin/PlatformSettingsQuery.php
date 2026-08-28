<?php

namespace App\Queries\Admin;

use App\Models\FeatureFlag;
use App\Models\PlatformConfiguration;
use App\Support\Authorization\ScopeReference;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PlatformSettingsQuery
{
    /**
     * @param  array{search?: string, environment?: string, classification?: string}  $filters
     * @return LengthAwarePaginator<PlatformConfiguration>
     */
    public function paginateConfigurations(
        ScopeReference $scope,
        array $filters,
        string $sort,
        int $perPage,
    ): LengthAwarePaginator {
        $query = PlatformConfiguration::query()->select([
            'id', 'public_id', 'key', 'value_type', 'classification', 'environment',
            'scope_type', 'scope_key', 'stored_value', 'updated_at',
        ]);
        $this->applyScope($query, $scope);
        $this->applyCommonFilters($query, $filters);

        if (isset($filters['classification'])) {
            $query->where('classification', $filters['classification']);
        }

        [$column, $direction] = $this->sort($sort);

        return $query->orderBy($column, $direction)->paginate($perPage);
    }

    /**
     * @param  array{search?: string, environment?: string, enabled?: bool}  $filters
     * @return LengthAwarePaginator<FeatureFlag>
     */
    public function paginateFeatureFlags(
        ScopeReference $scope,
        array $filters,
        string $sort,
        int $perPage,
    ): LengthAwarePaginator {
        $query = FeatureFlag::query()->select([
            'id', 'public_id', 'key', 'environment', 'scope_type', 'scope_key', 'is_enabled',
            'rollout_percentage', 'starts_at', 'ends_at', 'updated_at',
        ]);
        $this->applyScope($query, $scope);
        $this->applyCommonFilters($query, $filters);

        if (isset($filters['enabled'])) {
            $query->where('is_enabled', $filters['enabled']);
        }

        [$column, $direction] = $this->sort($sort);

        return $query->orderBy($column, $direction)->paginate($perPage);
    }

    /** @param Builder<PlatformConfiguration>|Builder<FeatureFlag> $query */
    private function applyScope(Builder $query, ScopeReference $scope): void
    {
        if ($scope->type === 'global' && $scope->key === 'platform') {
            return;
        }

        $query->where('scope_type', $scope->type)->where('scope_key', $scope->key);
    }

    /**
     * @param  Builder<PlatformConfiguration>|Builder<FeatureFlag>  $query
     * @param  array{search?: string, environment?: string}  $filters
     */
    private function applyCommonFilters(Builder $query, array $filters): void
    {
        if (isset($filters['search'])) {
            $query->where('key', 'like', '%'.trim($filters['search']).'%');
        }

        if (isset($filters['environment'])) {
            $query->where('environment', $filters['environment']);
        }
    }

    /** @return array{string, 'asc'|'desc'} */
    private function sort(string $sort): array
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return [ltrim($sort, '-'), $direction];
    }
}
