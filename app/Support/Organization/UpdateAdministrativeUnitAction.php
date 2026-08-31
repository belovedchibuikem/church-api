<?php

namespace App\Support\Organization;

use App\Models\AdministrativeUnit;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateAdministrativeUnitAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        AdministrativeUnit $unit,
        string $name,
        ?string $referenceCode,
        ?User $actor = null,
    ): AdministrativeUnit {
        $normalizedName = Str::squish($name);
        $normalizedReference = $referenceCode === null || trim($referenceCode) === ''
            ? null
            : Str::upper(Str::squish($referenceCode));

        if ($normalizedName === '' || Str::length($normalizedName) > 191) {
            throw new InvalidArgumentException('Administrative unit names must contain between 1 and 191 characters.');
        }

        return DB::transaction(function () use ($unit, $normalizedName, $normalizedReference, $actor): AdministrativeUnit {
            $locked = AdministrativeUnit::query()->lockForUpdate()->findOrFail($unit->getKey());

            if ($normalizedReference !== null) {
                $conflict = AdministrativeUnit::query()
                    ->where('country_id', $locked->country_id)
                    ->where('reference_code', $normalizedReference)
                    ->whereKeyNot($locked->getKey())
                    ->lockForUpdate()
                    ->exists();

                if ($conflict) {
                    throw new InvalidArgumentException('Administrative unit reference codes must be unique within a country.');
                }
            }

            $locked->name = $normalizedName;
            $locked->reference_code = $normalizedReference;
            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'organization.administrative_unit.updated',
                actor: $actor,
                targetType: 'administrative_unit',
                targetId: $locked->public_id,
                scopeType: 'administrative_unit',
                scopeId: $locked->public_id,
                metadata: ['name' => $locked->name],
            ));

            return $locked;
        }, attempts: 3);
    }
}
