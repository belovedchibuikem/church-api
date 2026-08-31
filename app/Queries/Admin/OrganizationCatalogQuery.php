<?php

namespace App\Queries\Admin;

use App\Church\ChurchMembershipStatus;
use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Country;
use App\Models\HomeChurch;
use App\Models\Location;
use App\Support\Authorization\ScopeReference;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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
        $query = Country::query()->select($this->countryColumns());
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

        if ($this->truthyFilter($filters['root'] ?? null)) {
            $query->whereNull('parent_id');
        }

        if ($this->truthyFilter($filters['nested'] ?? null)) {
            $query->whereNotNull('parent_id');
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

        if ($this->truthyFilter($filters['has_coordinates'] ?? null)) {
            $query->whereNotNull('latitude')->whereNotNull('longitude');
        }

        [$column, $direction] = $this->sort($sort, [
            'name' => 'name',
            'created_at' => 'created_at',
        ]);

        return $query->orderBy($column, $direction)->paginate($perPage);
    }

    public function findCountry(string $country): Country
    {
        $query = Country::query()->select($this->countryColumns());

        if (strlen($country) === 2) {
            return $query->where('iso_code', strtoupper($country))->firstOrFail();
        }

        $found = $query->where('public_id', $country)->first();
        if ($found !== null) {
            return $found;
        }

        $slug = str_replace('-', ' ', $country);

        return Country::query()
            ->select($this->countryColumns())
            ->whereRaw('lower(name) = ?', [strtolower($slug)])
            ->firstOrFail();
    }

    /** @return array<int, string> */
    private function countryColumns(): array
    {
        $columns = ['id', 'public_id', 'iso_code', 'name', 'created_at'];
        foreach (['local_name', 'calling_code', 'currency_code', 'default_timezone', 'locale'] as $column) {
            if (Schema::hasColumn('countries', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * @return array{units: int, root_units: int, locations: int, churches: int, home_churches: int, members: int, timezone: string|null}
     */
    public function countryStats(Country $country): array
    {
        $unitQuery = AdministrativeUnit::query()->where('country_id', $country->getKey());
        $churchIds = Church::query()
            ->where('administrative_unit_id', '!=', null)
            ->whereHas('administrativeUnit', fn (Builder $query) => $query->where('country_id', $country->getKey()))
            ->pluck('id');

        return [
            'units' => (clone $unitQuery)->count(),
            'root_units' => (clone $unitQuery)->whereNull('parent_id')->count(),
            'locations' => Location::query()->where('country_id', $country->getKey())->count(),
            'churches' => $churchIds->count(),
            'home_churches' => HomeChurch::query()->whereIn('church_id', $churchIds)->count(),
            'members' => ChurchMembership::query()
                ->whereIn('church_id', $churchIds)
                ->where('status', ChurchMembershipStatus::Active)
                ->count(),
            'timezone' => Location::query()
                ->where('country_id', $country->getKey())
                ->whereNotNull('timezone')
                ->select('timezone')
                ->groupBy('timezone')
                ->orderByRaw('count(*) desc')
                ->value('timezone'),
        ];
    }

    /**
     * @return array{children: int, locations: int, churches: int, home_churches: int, members: int}
     */
    public function unitStats(AdministrativeUnit $unit): array
    {
        $subtree = $this->administrativeUnitSubtreeIds($unit->public_id);
        $churchIds = Church::query()->whereIn('administrative_unit_id', $subtree)->pluck('id');

        return [
            'children' => AdministrativeUnit::query()->where('parent_id', $unit->getKey())->count(),
            'locations' => Location::query()->whereIn('administrative_unit_id', $subtree)->count(),
            'churches' => $churchIds->count(),
            'home_churches' => HomeChurch::query()->whereIn('church_id', $churchIds)->count(),
            'members' => ChurchMembership::query()
                ->whereIn('church_id', $churchIds)
                ->where('status', ChurchMembershipStatus::Active)
                ->count(),
        ];
    }

    /**
     * @return array{churches: int, home_churches: int, members: int}
     */
    public function locationStats(Location $location): array
    {
        $churchIds = Church::query()->where('location_id', $location->getKey())->pluck('id');

        return [
            'churches' => $churchIds->count(),
            'home_churches' => HomeChurch::query()->whereIn('church_id', $churchIds)->count(),
            'members' => ChurchMembership::query()
                ->whereIn('church_id', $churchIds)
                ->where('status', ChurchMembershipStatus::Active)
                ->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<AdministrativeUnit>
     */
    public function paginateTerritory(
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
            ])
            ->withCount(['churches', 'locations', 'children']);
        $this->applyAdministrativeUnitScope($query, $scope);

        if (isset($filters['country_id'])) {
            $query->where('country_id', $this->countryId($filters['country_id']) ?? 0);
        }

        if (isset($filters['search'])) {
            $search = trim($filters['search']);
            $query->where('name', 'like', "%{$search}%");
        }

        [$column, $direction] = $this->sort($sort, [
            'name' => 'name',
            'created_at' => 'created_at',
        ]);

        return $query->orderBy($column, $direction)->paginate($perPage);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function churchTree(ScopeReference $scope, int $limit = 400): array
    {
        $query = Church::query()->with([
            'administrativeUnit:id,public_id,name,country_id,parent_id',
            'administrativeUnit.country:id,public_id,iso_code,name',
            'homeChurches:id,public_id,church_id,name',
        ]);
        $this->applyChurchListScope($query, $scope);
        $churches = $query->orderBy('name')->limit($limit)->get();

        $byCountry = [];
        foreach ($churches as $church) {
            $country = $church->administrativeUnit?->country;
            if ($country === null) {
                continue;
            }
            $countryKey = $country->public_id;
            $byCountry[$countryKey] ??= [
                'id' => $country->public_id,
                'label' => $country->name,
                'level' => 'Country',
                'code' => $country->iso_code,
                'kind' => 'country',
                'children' => [],
            ];
            $unit = $church->administrativeUnit;
            $unitKey = $unit->public_id;
            $byCountry[$countryKey]['children'][$unitKey] ??= [
                'id' => $unit->public_id,
                'label' => $unit->name,
                'level' => 'Administrative Unit',
                'code' => $unit->public_id,
                'kind' => 'unit',
                'parent' => $country->name,
                'children' => [],
            ];
            $byCountry[$countryKey]['children'][$unitKey]['children'][] = [
                'id' => $church->public_id,
                'label' => $church->name,
                'level' => 'Church',
                'code' => $church->public_id,
                'kind' => 'group',
                'parent' => $unit->name,
                'churches' => 1,
                'homeChurches' => $church->homeChurches->count(),
                'children' => $church->homeChurches->map(fn (HomeChurch $home): array => [
                    'id' => $home->public_id,
                    'label' => $home->name,
                    'level' => 'Home Church',
                    'code' => $home->public_id,
                    'kind' => 'group',
                    'parent' => $church->name,
                ])->all(),
            ];
        }

        return array_values(array_map(function (array $country): array {
            $country['children'] = array_values($country['children']);

            return $country;
        }, $byCountry));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function homeChurchTree(ScopeReference $scope, int $limit = 400): array
    {
        $query = HomeChurch::query()->with(['church:id,public_id,name']);
        if ($scope->type === 'global' && $scope->key === 'platform') {
            // unscoped
        } elseif ($scope->type === 'church') {
            $query->whereHas('church', fn (Builder $churchQuery) => $churchQuery->where('public_id', $scope->key));
        } elseif ($scope->type === 'home_church') {
            $query->where('public_id', $scope->key);
        } else {
            $churchIds = Church::query();
            $this->applyChurchListScope($churchIds, $scope);
            $query->whereIn('church_id', $churchIds->select('id'));
        }

        $homes = $query->orderBy('name')->limit($limit)->get();
        $byChurch = [];
        foreach ($homes as $home) {
            $church = $home->church;
            if ($church === null) {
                continue;
            }
            $key = $church->public_id;
            $byChurch[$key] ??= [
                'id' => $church->public_id,
                'label' => $church->name,
                'level' => 'Main Church',
                'code' => $church->public_id,
                'kind' => 'group',
                'children' => [],
            ];
            $byChurch[$key]['children'][] = [
                'id' => $home->public_id,
                'label' => $home->name,
                'level' => 'Home Church',
                'code' => $home->public_id,
                'kind' => 'group',
                'parent' => $church->name,
            ];
        }

        return array_values($byChurch);
    }

    /** @param Builder<Church> $query */
    private function applyChurchListScope(Builder $query, ScopeReference $scope): void
    {
        if ($scope->type === 'global' && $scope->key === 'platform') {
            return;
        }

        match ($scope->type) {
            'country' => $query->whereHas('administrativeUnit.country', fn (Builder $countryQuery) => $countryQuery->where('public_id', $scope->key)),
            'administrative_unit' => $query->whereIn('administrative_unit_id', $this->administrativeUnitSubtreeIds($scope->key)),
            'church' => $query->where('public_id', $scope->key),
            'home_church' => $query->whereHas('homeChurches', fn (Builder $homeQuery) => $homeQuery->where('public_id', $scope->key)),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function truthyFilter(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'yes') {
            return true;
        }

        return false;
    }

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
