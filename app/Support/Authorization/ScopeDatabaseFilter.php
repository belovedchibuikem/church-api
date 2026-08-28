<?php

namespace App\Support\Authorization;

use App\Models\AdministrativeUnit;
use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;

class ScopeDatabaseFilter
{
    public function apply(
        Builder $query,
        ScopeReference $scope,
        string $typeColumn = 'scope_type',
        string $keyColumn = 'scope_key',
    ): Builder {
        if ($scope->type === 'global' && $scope->key === 'platform') {
            return $query;
        }

        if ($scope->type === 'country') {
            $countryId = Country::query()
                ->where('public_id', $scope->key)
                ->value('id');

            if ($countryId === null) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function (Builder $scopeQuery) use (
                $typeColumn,
                $keyColumn,
                $scope,
                $countryId,
            ): void {
                $scopeQuery
                    ->where(function (Builder $countryQuery) use ($typeColumn, $keyColumn, $scope): void {
                        $countryQuery
                            ->where($typeColumn, 'country')
                            ->where($keyColumn, $scope->key);
                    })
                    ->orWhere(function (Builder $unitQuery) use ($typeColumn, $keyColumn, $countryId): void {
                        $unitQuery
                            ->where($typeColumn, 'administrative_unit')
                            ->whereIn($keyColumn, AdministrativeUnit::query()
                                ->select('public_id')
                                ->where('country_id', $countryId));
                    });
            });
        }

        if ($scope->type === 'administrative_unit') {
            $visibleUnitIds = $this->administrativeUnitAndDescendantPublicIds($scope->key);

            return $visibleUnitIds === []
                ? $query->whereRaw('1 = 0')
                : $query
                    ->where($typeColumn, 'administrative_unit')
                    ->whereIn($keyColumn, $visibleUnitIds);
        }

        return $query
            ->where($typeColumn, $scope->type)
            ->where($keyColumn, $scope->key);
    }

    /** @return array<int, string> */
    private function administrativeUnitAndDescendantPublicIds(string $rootPublicId): array
    {
        $root = AdministrativeUnit::query()
            ->select(['id', 'public_id'])
            ->where('public_id', $rootPublicId)
            ->first();

        if ($root === null) {
            return [];
        }

        $publicIds = [$root->public_id];
        $frontier = [$root->getKey()];
        $visited = [$root->getKey() => true];

        while ($frontier !== []) {
            $children = AdministrativeUnit::query()
                ->select(['id', 'public_id'])
                ->whereIn('parent_id', $frontier)
                ->get();
            $frontier = [];

            foreach ($children as $child) {
                if (isset($visited[$child->getKey()])) {
                    continue;
                }

                $visited[$child->getKey()] = true;
                $frontier[] = $child->getKey();
                $publicIds[] = $child->public_id;
            }
        }

        return $publicIds;
    }
}
