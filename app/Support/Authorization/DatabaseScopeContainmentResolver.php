<?php

namespace App\Support\Authorization;

use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\Country;
use App\Models\Crusade;
use App\Models\HomeChurch;
use App\Models\User;
use App\Support\Authorization\Contracts\ScopeContainmentResolver;

class DatabaseScopeContainmentResolver implements ScopeContainmentResolver
{
    private const GLOBAL_SCOPE = 'global';

    private const GLOBAL_SCOPE_KEY = 'platform';

    private const OWN_RECORD_SCOPE = 'own_record';

    private const COUNTRY_SCOPE = 'country';

    private const ADMINISTRATIVE_UNIT_SCOPE = 'administrative_unit';

    /** @var array<int, string> */
    private const ORGANIZATIONAL_SCOPES = [
        self::COUNTRY_SCOPE,
        self::ADMINISTRATIVE_UNIT_SCOPE,
        'church',
        'home_church',
        'kca_cohort',
        'mission_crusade',
    ];

    public function contains(
        ScopeReference $assignedScope,
        ScopeReference $requestedScope,
        User $actor,
    ): bool {
        if ($assignedScope->type === self::OWN_RECORD_SCOPE || $requestedScope->type === self::OWN_RECORD_SCOPE) {
            return $this->matchesOwnRecord($assignedScope, $requestedScope, $actor);
        }

        if (
            $assignedScope->type === $requestedScope->type
            && $assignedScope->key === $requestedScope->key
        ) {
            return true;
        }

        if (
            $assignedScope->type === self::GLOBAL_SCOPE
            && $assignedScope->key === self::GLOBAL_SCOPE_KEY
            && in_array($requestedScope->type, self::ORGANIZATIONAL_SCOPES, true)
        ) {
            return true;
        }

        if (
            $assignedScope->type === self::COUNTRY_SCOPE
            && $requestedScope->type === self::ADMINISTRATIVE_UNIT_SCOPE
        ) {
            return $this->countryContainsUnit($assignedScope->key, $requestedScope->key);
        }

        if (
            $assignedScope->type === self::ADMINISTRATIVE_UNIT_SCOPE
            && $requestedScope->type === self::ADMINISTRATIVE_UNIT_SCOPE
        ) {
            return $this->unitContainsDescendant($assignedScope->key, $requestedScope->key);
        }

        if (in_array($requestedScope->type, ['church', 'home_church', 'mission_crusade'], true)) {
            return $this->organizationalScopeContainsRecord($assignedScope, $requestedScope);
        }

        return false;
    }

    private function organizationalScopeContainsRecord(
        ScopeReference $assignedScope,
        ScopeReference $requestedScope,
    ): bool {
        $administrativeUnitId = match ($requestedScope->type) {
            'church' => Church::query()->where('public_id', $requestedScope->key)->value('administrative_unit_id'),
            'home_church' => HomeChurch::query()->where('public_id', $requestedScope->key)->value('administrative_unit_id'),
            'mission_crusade' => Crusade::query()
                ->where('public_id', $requestedScope->key)
                ->with('location:id,administrative_unit_id')
                ->first()?->location?->administrative_unit_id,
            default => null,
        };

        if ($administrativeUnitId === null) {
            return false;
        }

        $unit = AdministrativeUnit::query()->select(['public_id', 'country_id'])->find($administrativeUnitId);

        if ($unit === null) {
            return false;
        }

        return match ($assignedScope->type) {
            self::COUNTRY_SCOPE => Country::query()
                ->where('public_id', $assignedScope->key)
                ->whereKey($unit->country_id)
                ->exists(),
            self::ADMINISTRATIVE_UNIT_SCOPE => $assignedScope->key === $unit->public_id
                || $this->unitContainsDescendant($assignedScope->key, $unit->public_id),
            'church' => $requestedScope->type === 'home_church'
                && HomeChurch::query()
                    ->where('public_id', $requestedScope->key)
                    ->whereHas('church', fn ($query) => $query->where('public_id', $assignedScope->key))
                    ->exists(),
            default => false,
        };
    }

    private function matchesOwnRecord(
        ScopeReference $assignedScope,
        ScopeReference $requestedScope,
        User $actor,
    ): bool {
        if (
            $assignedScope->type !== self::OWN_RECORD_SCOPE
            || $requestedScope->type !== self::OWN_RECORD_SCOPE
            || $assignedScope->key !== $requestedScope->key
        ) {
            return false;
        }

        $actor->loadMissing('person:id,public_id');

        return $requestedScope->key === $actor->public_id
            || ($actor->person !== null && $requestedScope->key === $actor->person->public_id);
    }

    private function countryContainsUnit(string $countryPublicId, string $unitPublicId): bool
    {
        $countryId = Country::query()->where('public_id', $countryPublicId)->value('id');

        if ($countryId === null) {
            return false;
        }

        return AdministrativeUnit::query()
            ->where('public_id', $unitPublicId)
            ->where('country_id', $countryId)
            ->exists();
    }

    private function unitContainsDescendant(string $assignedPublicId, string $requestedPublicId): bool
    {
        $assignedUnit = AdministrativeUnit::query()
            ->select(['id', 'country_id'])
            ->where('public_id', $assignedPublicId)
            ->first();
        $requestedUnit = AdministrativeUnit::query()
            ->select(['id', 'country_id', 'parent_id'])
            ->where('public_id', $requestedPublicId)
            ->first();

        if (
            $assignedUnit === null
            || $requestedUnit === null
            || $assignedUnit->country_id !== $requestedUnit->country_id
        ) {
            return false;
        }

        $parentId = $requestedUnit->parent_id;
        $visitedIds = [$requestedUnit->getKey() => true];

        while ($parentId !== null) {
            if ($parentId === $assignedUnit->getKey()) {
                return true;
            }

            if (isset($visitedIds[$parentId])) {
                return false;
            }

            $visitedIds[$parentId] = true;
            $parent = AdministrativeUnit::query()
                ->select(['id', 'country_id', 'parent_id'])
                ->find($parentId);

            if ($parent === null || $parent->country_id !== $assignedUnit->country_id) {
                return false;
            }

            $parentId = $parent->parent_id;
        }

        return false;
    }
}
