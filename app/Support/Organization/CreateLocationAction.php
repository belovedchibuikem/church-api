<?php

namespace App\Support\Organization;

use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Models\Location;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateLocationAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(LocationData $data, ?User $actor = null): Location
    {
        return DB::transaction(function () use ($data, $actor): Location {
            $lockedCountry = Country::query()->lockForUpdate()->findOrFail($data->country->getKey());
            $lockedUnit = $data->administrativeUnit === null
                ? null
                : AdministrativeUnit::query()
                    ->lockForUpdate()
                    ->findOrFail($data->administrativeUnit->getKey());

            if ($lockedUnit !== null && $lockedUnit->country_id !== $lockedCountry->getKey()) {
                throw new InvalidArgumentException('A location administrative unit must belong to its country.');
            }

            $location = Location::query()->create([
                'country_id' => $lockedCountry->getKey(),
                'administrative_unit_id' => $lockedUnit?->getKey(),
                'name' => $data->name,
                'address_line_one' => $data->addressLineOne,
                'address_line_two' => $data->addressLineTwo,
                'locality' => $data->locality,
                'postal_code' => $data->postalCode,
                'timezone' => $data->timezone,
                'latitude' => $data->coordinates->latitude,
                'longitude' => $data->coordinates->longitude,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'organization.location.created',
                actor: $actor,
                targetType: 'location',
                targetId: $location->public_id,
                scopeType: $lockedUnit === null ? 'country' : 'administrative_unit',
                scopeId: $lockedUnit?->public_id ?? $lockedCountry->public_id,
                metadata: ['country_id' => $lockedCountry->public_id],
            ));

            return $location;
        }, attempts: 3);
    }
}
