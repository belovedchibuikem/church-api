<?php

namespace App\Support\Church;

use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\Location;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateChurchAction
{
    public function __construct(
        private ChurchLocationValidator $locationValidator,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        Church $church,
        string $name,
        Location $location,
        AdministrativeUnit $administrativeUnit,
        ?User $actor = null,
    ): Church {
        $normalizedName = Str::squish($name);

        if ($normalizedName === '' || Str::length($normalizedName) > 191) {
            throw new InvalidArgumentException('Church names must contain between 1 and 191 characters.');
        }

        return DB::transaction(function () use (
            $church,
            $normalizedName,
            $location,
            $administrativeUnit,
            $actor,
        ): Church {
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->getKey());
            $lockedLocation = Location::query()->lockForUpdate()->findOrFail($location->getKey());
            $lockedUnit = AdministrativeUnit::query()
                ->lockForUpdate()
                ->findOrFail($administrativeUnit->getKey());
            $this->locationValidator->assertAligned($lockedLocation, $lockedUnit);

            $duplicateExists = Church::query()
                ->whereBelongsTo($lockedLocation)
                ->where('name', $normalizedName)
                ->whereKeyNot($lockedChurch->getKey())
                ->lockForUpdate()
                ->exists();

            if ($duplicateExists) {
                throw new InvalidArgumentException('A church with this name already exists at the location.');
            }

            $lockedChurch->forceFill([
                'location_id' => $lockedLocation->getKey(),
                'administrative_unit_id' => $lockedUnit->getKey(),
                'name' => $normalizedName,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'church.updated',
                actor: $actor,
                targetType: 'church',
                targetId: $lockedChurch->public_id,
                scopeType: 'church',
                scopeId: $lockedChurch->public_id,
                metadata: [
                    'location_id' => $lockedLocation->public_id,
                    'administrative_unit_id' => $lockedUnit->public_id,
                ],
            ));

            return $lockedChurch->refresh();
        }, attempts: 3);
    }
}
