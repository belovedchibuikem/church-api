<?php

namespace App\Support\Organization;

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class MoveAdministrativeUnitAction
{
    public function __construct(
        private AdministrativeHierarchyValidator $hierarchyValidator,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        AdministrativeUnit $unit,
        ?AdministrativeUnit $newParent,
        ?User $actor = null,
    ): AdministrativeUnit {
        return DB::transaction(function () use ($unit, $newParent, $actor): AdministrativeUnit {
            $lockedUnit = AdministrativeUnit::query()->lockForUpdate()->findOrFail($unit->getKey());
            $lockedCountry = Country::query()->lockForUpdate()->findOrFail($lockedUnit->country_id);
            $lockedLevel = AdministrativeLevel::query()
                ->lockForUpdate()
                ->findOrFail($lockedUnit->administrative_level_id);
            $lockedParent = $newParent === null
                ? null
                : AdministrativeUnit::query()->lockForUpdate()->findOrFail($newParent->getKey());

            if ($lockedUnit->parent_id === $lockedParent?->getKey()) {
                return $lockedUnit;
            }

            $this->hierarchyValidator->assertValidParent(
                $lockedCountry,
                $lockedLevel,
                $lockedParent,
                $lockedUnit,
            );

            $previousParentId = $lockedUnit->parent_id;
            $previousParentPublicId = $previousParentId === null
                ? null
                : AdministrativeUnit::query()
                    ->whereKey($previousParentId)
                    ->lockForUpdate()
                    ->valueOrFail('public_id');
            $lockedUnit->parent()->associate($lockedParent);
            $lockedUnit->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'organization.administrative_unit.parent_changed',
                actor: $actor,
                targetType: 'administrative_unit',
                targetId: $lockedUnit->public_id,
                scopeType: 'country',
                scopeId: $lockedCountry->public_id,
                metadata: [
                    'previous_parent_id' => $previousParentPublicId,
                    'new_parent_id' => $lockedParent?->public_id,
                ],
            ));

            return $lockedUnit;
        }, attempts: 3);
    }
}
