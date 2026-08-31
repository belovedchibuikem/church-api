<?php

namespace App\Support\Organization;

use App\Models\Country;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateCountryAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    /**
     * @param  array<string, mixed>  $profile
     */
    public function handle(Country $country, string $name, ?User $actor = null, array $profile = []): Country
    {
        $normalizedName = Str::squish($name);
        $profile = CountryProfile::normalize($profile);

        if ($normalizedName === '' || Str::length($normalizedName) > 191) {
            throw new InvalidArgumentException('Country names must contain between 1 and 191 characters.');
        }

        return DB::transaction(function () use ($country, $normalizedName, $actor, $profile): Country {
            $locked = Country::query()->lockForUpdate()->findOrFail($country->getKey());
            $profile = CountryProfile::persistable($profile);
            $from = [
                'name' => $locked->name,
                'local_name' => $locked->local_name,
                'calling_code' => $locked->calling_code,
                'currency_code' => $locked->currency_code,
                'default_timezone' => $locked->default_timezone,
                'locale' => $locked->locale,
            ];
            $locked->fill(['name' => $normalizedName, ...$profile]);
            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'organization.country.updated',
                actor: $actor,
                targetType: 'country',
                targetId: $locked->public_id,
                scopeType: 'country',
                scopeId: $locked->public_id,
                metadata: ['from' => $from, 'to' => [
                    'name' => $locked->name,
                    'local_name' => $locked->local_name,
                    'calling_code' => $locked->calling_code,
                    'currency_code' => $locked->currency_code,
                    'default_timezone' => $locked->default_timezone,
                    'locale' => $locked->locale,
                ]],
            ));

            return $locked;
        }, attempts: 3);
    }
}
