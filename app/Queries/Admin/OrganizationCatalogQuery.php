<?php

namespace App\Queries\Admin;

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Models\Location;
use App\Support\Authorization\ScopeReference;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OrganizationCatalogQuery
{
    /**
     * @param  array{search?: string}  $filters
     * @return LengthAwarePaginator<Country>
     */
    public function paginateCountries(
        ScopeReference $scope,
        array $filters,
        string $sort,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Country::query()->select(['id', 'public_id', 'iso_code', 'name', 'created_at']);
        $this->applyCountryScope($query, $scope);

        if (isset($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('iso_code', 'like', "%{$search}%");
            });
        }

        [$column, $direction] = $this->sort($sort, [
            'name' => 'name',
            'iso_code' => 'iso_code',
        ]);

        return $query->orderBy($column, $direction)->paginate($perPage);
    }

    /** @return Collection<int, AdministrativeLevel> */
    public function levels(Country $country): Collection
    {
        return AdministrativeLevel::query()
            ->select(['id', 'public_id', 'country_id', 'code', 'name', 'sort_order'])
            ->with('country:id,public_id')
            ->whereBelongsTo($country)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @param  array{search?: string, country_id?: string, level_id?: string, parent_id?: string}  $filters
     * @return LengthAwarePaginator<AdministrativeUnit>
     */
    public function paginateUnits(
        ScopeReference $scope,
        array $filters,
        string $sort,
        int $perPage,
    ): LengthAwarePaginator {
        $query = AdministrativeUnit::query()
            ->select(['id', 'public_id', 'country_id', 'administrative_level_id', 'parent_id', 'name', 'reference_code', 'created_at'])
            ->with([
                'country:id,public_id,iso_code,name',
                'administrativeLevel:id,public_id,code,name,sort_order',
                'parent:id,public_id,name',
            ]);
        $this->applyAdministrativeUnitScope($query, $scope);

        if (isset($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('reference_code', 'like', "%{$search}%");
            });
        }

        if (isset($filters['country_id'])) {
            $query->where('country_id', $this->countryId($filters['country_id']) ?? 0);
        }

        if (isset($filters['level_id'])) {
            $levelId = AdministrativeLevel::query()->where('public_id', $filters['level_id'])->value('id');
            $query->where('administrative_level_id', $levelId ?? 0);
        }

        if (isset($filters['parent_id'])) {
            $parentId = AdministrativeUnit::query()->where('public_id', $filters['parent_id'])->value('id');
            $query->where('parent_id', $parentId ?? 0);
        }

        [$column, $direction] = $this->sort($sort, [
            'name' => 'name',
            'created_at' => 'created_at',
        ]);

        return $query->orderBy($column, $direction)->paginate($perPage);
    }

    /**
     * @param  array{search?: string, country_id?: string, administrative_unit_id?: string, timezone?: string}  $filters
     * @return LengthAwarePaginator<Location>
     */
    public function paginateLocations(
        ScopeReference $scope,
        array $filters,
        string $sort,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Location::query()
            ->select([
                'id', 'public_id', 'country_id', 'administrative_unit_id', 'name', 'address_line_one',
                'address_line_two', 'locality', 'postal_code', 'timezone', 'latitude', 'longitude', 'created_at',
            ])
            ->with([
                'country:id,public_id,iso_code,name',
                'administrativeUnit:id,public_id,name',
            ]);
        $this->applyLocationScope($query, $scope);

        if (isset($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('locality', 'like', "%{$search}%");
            });
        }

        if (isset($filters['country_id'])) {
            $query->where('country_id', $this->countryId($filters['country_id']) ?? 0);
        }

        if (isset($filters['administrative_unit_id'])) {
            $unitId = AdministrativeUnit::query()->where('public_id', $filters['administrative_unit_id'])->value('id');
            $query->where('administrative_unit_id', $unitId ?? 0);
        }

        if (isset($filters['timezone'])) {
            $query->where('timezone', $filters['timezone']);
        }

        [$column, $direction] = $this->sort($sort, [
            'name' => 'name',
            'created_at' => 'created_at',
        ]);

        return $query->orderBy($column, $direction)->paginate($perPage);
    }

    /** @param Builder<Country> $query */
    private function applyCountryScope(Builder $query, ScopeReference $scope): void
    {
        if ($scope->type === 'global' && $scope->key === 'platform') {
            return;
        }

        if ($scope->type === 'country') {
            $query->where('public_id', $scope->key);

            return;
        }

        if ($scope->type === 'administrative_unit') {
            $countryId = AdministrativeUnit::query()->where('public_id', $scope->key)->value('country_id');
            $query->whereKey($countryId ?? 0);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /** @param Builder<AdministrativeUnit> $query */
    private function applyAdministrativeUnitScope(Builder $query, ScopeReference $scope): void
    {
        if ($scope->type === 'global' && $scope->key === 'platform') {
            return;
        }

        if ($scope->type === 'country') {
            $query->where('country_id', $this->countryId($scope->key) ?? 0);

            return;
        }

        if ($scope->type === 'administrative_unit') {
            $query->whereIn('id', $this->administrativeUnitSubtreeIds($scope->key));

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /** @param Builder<Location> $query */
    private function applyLocationScope(Builder $query, ScopeReference $scope): void
    {
        if ($scope->type === 'global' && $scope->key === 'platform') {
            return;
        }

        if ($scope->type === 'country') {
            $query->where('country_id', $this->countryId($scope->key) ?? 0);

            return;
        }

        if ($scope->type === 'administrative_unit') {
            $query->whereIn('administrative_unit_id', $this->administrativeUnitSubtreeIds($scope->key));

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /** @return array<int, int> */
    private function administrativeUnitSubtreeIds(string $publicId): array
    {
        $root = AdministrativeUnit::query()->select(['id', 'country_id'])->where('public_id', $publicId)->first();

        if ($root === null) {
            return [];
        }

        $units = AdministrativeUnit::query()
            ->select(['id', 'parent_id'])
            ->where('country_id', $root->country_id)
            ->get();
        $allowed = [$root->getKey() => true];
        $changed = true;

        while ($changed) {
            $changed = false;

            foreach ($units as $unit) {
                if (! isset($allowed[$unit->getKey()]) && isset($allowed[$unit->parent_id])) {
                    $allowed[$unit->getKey()] = true;
                    $changed = true;
                }
            }
        }

        return array_map('intval', array_keys($allowed));
    }

    private function countryId(string $publicId): ?int
    {
        $id = Country::query()->where('public_id', $publicId)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  array<string, string>  $columns
     * @return array{string, 'asc'|'desc'}
     */
    private function sort(string $sort, array $columns): array
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $key = ltrim($sort, '-');

        return [$columns[$key], $direction];
    }
}
