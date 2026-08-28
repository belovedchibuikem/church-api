<?php

namespace App\Support\Organization;

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use InvalidArgumentException;

class AdministrativeHierarchyValidator
{
    public function assertValidParent(
        Country $country,
        AdministrativeLevel $level,
        ?AdministrativeUnit $parent,
        ?AdministrativeUnit $movingUnit = null,
    ): void {
        if ($level->country_id !== $country->getKey()) {
            throw new InvalidArgumentException('The administrative level must belong to the unit country.');
        }

        if ($parent !== null && $parent->country_id !== $country->getKey()) {
            throw new InvalidArgumentException('The parent administrative unit must belong to the same country.');
        }

        if ($movingUnit !== null && $parent !== null) {
            $this->assertDoesNotCreateCycle($movingUnit, $parent);
        }

        $previousLevel = AdministrativeLevel::query()
            ->whereBelongsTo($country)
            ->where('sort_order', '<', $level->sort_order)
            ->orderByDesc('sort_order')
            ->lockForUpdate()
            ->first();

        if ($previousLevel === null) {
            if ($parent !== null) {
                throw new InvalidArgumentException('Units at the first administrative level cannot have a parent.');
            }

            return;
        }

        if ($parent === null || $parent->administrative_level_id !== $previousLevel->getKey()) {
            throw new InvalidArgumentException('The parent must belong to the immediately preceding administrative level.');
        }
    }

    private function assertDoesNotCreateCycle(
        AdministrativeUnit $movingUnit,
        AdministrativeUnit $proposedParent,
    ): void {
        $current = $proposedParent;
        $visitedIds = [];

        while (true) {
            $currentId = $current->getKey();

            if ($currentId === $movingUnit->getKey() || isset($visitedIds[$currentId])) {
                throw new InvalidArgumentException('Administrative unit parentage cannot contain a cycle.');
            }

            $visitedIds[$currentId] = true;

            if ($current->parent_id === null) {
                return;
            }

            $current = AdministrativeUnit::query()
                ->select(['id', 'country_id', 'administrative_level_id', 'parent_id'])
                ->lockForUpdate()
                ->findOrFail($current->parent_id);

            if ($current->country_id !== $movingUnit->country_id) {
                throw new InvalidArgumentException('Administrative unit ancestry cannot cross countries.');
            }
        }
    }
}
