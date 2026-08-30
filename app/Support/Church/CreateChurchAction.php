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

class CreateChurchAction
{
    public function __construct(
        private ChurchLocationValidator $locationValidator,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
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
            $normalizedName,
            $location,
            $administrativeUnit,
            $actor,
        ): Church {
            $lockedLocation = Location::query()->lockForUpdate()->findOrFail($location->getKey());
            $lockedUnit = AdministrativeUnit::query()
                ->lockForUpdate()
                ->findOrFail($administrativeUnit->getKey());
            $this->locationValidator->assertAligned($lockedLocation, $lockedUnit);

            $duplicateExists = Church::query()
                ->whereBelongsTo($lockedLocation)
                ->where('name', $normalizedName)
                ->lockForUpdate()
                ->exists();

            if ($duplicateExists) {
                throw new InvalidArgumentException('A church with this name already exists at the location.');
            }

            $church = Church::query()->create([
                'location_id' => $lockedLocation->getKey(),
                'administrative_unit_id' => $lockedUnit->getKey(),
                'name' => $normalizedName,
                'published_at' => now()->utc(),
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'church.created',
                actor: $actor,
                targetType: 'church',
                targetId: $church->public_id,
                scopeType: 'church',
                scopeId: $church->public_id,
                metadata: [
                    'location_id' => $lockedLocation->public_id,
                    'administrative_unit_id' => $lockedUnit->public_id,
                ],
            ));

            return $church;
        }, attempts: 3);
    }
}
