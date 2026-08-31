<?php

namespace App\Support\Organization;

use App\Models\Location;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateLocationAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(Location $location, LocationData $data, ?User $actor = null): Location
    {
        return DB::transaction(function () use ($location, $data, $actor): Location {
            $locked = Location::query()->lockForUpdate()->findOrFail($location->getKey());

            if ($data->country->getKey() !== $locked->country_id) {
                throw new InvalidArgumentException('A location cannot move to a different country.');
            }

            $unit = $data->administrativeUnit;
            if ($unit !== null && $unit->country_id !== $locked->country_id) {
                throw new InvalidArgumentException('A location administrative unit must belong to its country.');
            }

            $locked->fill([
                'administrative_unit_id' => $unit?->getKey(),
                'name' => $data->name,
                'address_line_one' => $data->addressLineOne,
                'address_line_two' => $data->addressLineTwo,
                'locality' => $data->locality,
                'postal_code' => $data->postalCode,
                'timezone' => $data->timezone,
                'latitude' => $data->coordinates->latitude,
                'longitude' => $data->coordinates->longitude,
            ]);
            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'organization.location.updated',
                actor: $actor,
                targetType: 'location',
                targetId: $locked->public_id,
                scopeType: 'country',
                scopeId: $data->country->public_id,
                metadata: ['name' => $locked->name],
            ));

            return $locked;
        }, attempts: 3);
    }
}
