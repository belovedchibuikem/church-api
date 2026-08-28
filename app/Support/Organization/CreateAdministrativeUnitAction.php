<?php

namespace App\Support\Organization;

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateAdministrativeUnitAction
{
    public function __construct(
        private AdministrativeHierarchyValidator $hierarchyValidator,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        Country $country,
        AdministrativeLevel $level,
        string $name,
        ?AdministrativeUnit $parent = null,
        ?string $referenceCode = null,
        ?User $actor = null,
    ): AdministrativeUnit {
        $normalizedName = Str::squish($name);
        $normalizedReferenceCode = $this->normalizeReferenceCode($referenceCode);

        if ($normalizedName === '' || Str::length($normalizedName) > 191) {
            throw new InvalidArgumentException('Administrative unit names must contain between 1 and 191 characters.');
        }

        return DB::transaction(function () use (
            $country,
            $level,
            $normalizedName,
            $parent,
            $normalizedReferenceCode,
            $actor,
        ): AdministrativeUnit {
            $lockedCountry = Country::query()->lockForUpdate()->findOrFail($country->getKey());
            $lockedLevel = AdministrativeLevel::query()->lockForUpdate()->findOrFail($level->getKey());
            $lockedParent = $parent === null
                ? null
                : AdministrativeUnit::query()->lockForUpdate()->findOrFail($parent->getKey());

            $this->hierarchyValidator->assertValidParent(
                $lockedCountry,
                $lockedLevel,
                $lockedParent,
            );

            if ($normalizedReferenceCode !== null) {
                $existingUnit = AdministrativeUnit::query()
                    ->whereBelongsTo($lockedCountry)
                    ->where('reference_code', $normalizedReferenceCode)
                    ->lockForUpdate()
                    ->first();

                if ($existingUnit !== null) {
                    if (
                        $existingUnit->administrative_level_id === $lockedLevel->getKey()
                        && $existingUnit->parent_id === $lockedParent?->getKey()
                        && $existingUnit->name === $normalizedName
                    ) {
                        return $existingUnit;
                    }

                    throw new InvalidArgumentException('Administrative unit reference codes must be unique within a country.');
                }
            }

            $unit = AdministrativeUnit::query()->create([
                'country_id' => $lockedCountry->getKey(),
                'administrative_level_id' => $lockedLevel->getKey(),
                'parent_id' => $lockedParent?->getKey(),
                'name' => $normalizedName,
                'reference_code' => $normalizedReferenceCode,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'organization.administrative_unit.created',
                actor: $actor,
                targetType: 'administrative_unit',
                targetId: $unit->public_id,
                scopeType: 'country',
                scopeId: $lockedCountry->public_id,
                metadata: [
                    'administrative_level_id' => $lockedLevel->public_id,
                    'parent_id' => $lockedParent?->public_id,
                    'reference_code' => $normalizedReferenceCode,
                ],
            ));

            return $unit;
        }, attempts: 3);
    }

    private function normalizeReferenceCode(?string $referenceCode): ?string
    {
        if ($referenceCode === null || Str::squish($referenceCode) === '') {
            return null;
        }

        $normalized = Str::of($referenceCode)->trim()->upper()->toString();

        if (
            Str::length($normalized) > 100
            || ! Str::isMatch('/\A[A-Z0-9][A-Z0-9._:-]*\z/', $normalized)
        ) {
            throw new InvalidArgumentException('Administrative unit reference codes contain invalid characters.');
        }

        return $normalized;
    }
}
