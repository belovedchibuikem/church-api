<?php

namespace App\Support\Church;

use App\Church\HomeChurchStatus;
use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\HomeChurch;
use App\Models\Location;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateHomeChurchAction
{
    public function __construct(
        private ChurchLocationValidator $locationValidator,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        Church $church,
        Person $leader,
        Location $location,
        AdministrativeUnit $administrativeUnit,
        string $name,
        ?User $actor = null,
    ): HomeChurch {
        $normalizedName = Str::squish($name);

        if ($normalizedName === '' || Str::length($normalizedName) > 191) {
            throw new InvalidArgumentException('Home church names must contain between 1 and 191 characters.');
        }

        return DB::transaction(function () use (
            $church,
            $leader,
            $location,
            $administrativeUnit,
            $normalizedName,
            $actor,
        ): HomeChurch {
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->getKey());
            $lockedLocation = Location::query()->lockForUpdate()->findOrFail($location->getKey());
            $lockedUnit = AdministrativeUnit::query()
                ->lockForUpdate()
                ->findOrFail($administrativeUnit->getKey());
            $this->locationValidator->assertAligned($lockedLocation, $lockedUnit);

            $duplicateExists = HomeChurch::query()
                ->where('location_id', $lockedLocation->getKey())
                ->where('name', $normalizedName)
                ->lockForUpdate()
                ->exists();

            if ($duplicateExists) {
                throw new InvalidArgumentException('A home church with this name already exists at the location.');
            }

            $homeChurch = HomeChurch::query()->create([
                'church_id' => $lockedChurch->getKey(),
                'leader_person_id' => $leader->getKey(),
                'location_id' => $lockedLocation->getKey(),
                'administrative_unit_id' => $lockedUnit->getKey(),
                'name' => $normalizedName,
                'status' => HomeChurchStatus::Active,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'home_church.created',
                actor: $actor,
                targetType: 'home_church',
                targetId: $homeChurch->public_id,
                scopeType: 'home_church',
                scopeId: $homeChurch->public_id,
                metadata: [
                    'church_id' => $lockedChurch->public_id,
                    'leader_person_id' => $leader->public_id,
                ],
            ));

            return $homeChurch;
        }, attempts: 3);
    }
}
