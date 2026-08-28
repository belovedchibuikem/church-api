<?php

namespace App\Support\Organization;

use App\Models\Country;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateCountryAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(string $isoCode, string $name, ?User $actor = null): Country
    {
        $countryCode = new IsoCountryCode($isoCode);
        $normalizedName = Str::squish($name);

        if ($normalizedName === '' || Str::length($normalizedName) > 191) {
            throw new InvalidArgumentException('Country names must contain between 1 and 191 characters.');
        }

        return DB::transaction(function () use ($countryCode, $normalizedName, $actor): Country {
            $existingCountry = Country::query()
                ->where('iso_code', $countryCode->value)
                ->lockForUpdate()
                ->first();

            if ($existingCountry !== null) {
                if ($existingCountry->name !== $normalizedName) {
                    throw new InvalidArgumentException('The ISO country code is already assigned to another name.');
                }

                return $existingCountry;
            }

            $country = Country::query()->create([
                'iso_code' => $countryCode->value,
                'name' => $normalizedName,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'organization.country.created',
                actor: $actor,
                targetType: 'country',
                targetId: $country->public_id,
                scopeType: 'country',
                scopeId: $country->public_id,
                metadata: ['iso_code' => $country->iso_code],
            ));

            return $country;
        }, attempts: 3);
    }
}
