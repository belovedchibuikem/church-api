<?php

namespace App\Support\Identity;

use App\Models\Person;
use App\Models\PersonProfile;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class UpdatePersonProfileAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array{given_name: string, middle_name?: string|null, family_name: string, preferred_name?: string|null}  $attributes
     */
    public function handle(Person $person, array $attributes, ?User $actor = null): PersonProfile
    {
        return DB::transaction(function () use ($person, $attributes, $actor): PersonProfile {
            $lockedPerson = Person::query()->lockForUpdate()->findOrFail($person->getKey());
            $profile = $lockedPerson->profile()->firstOrFail();
            $profile->fill([
                'given_name' => $attributes['given_name'],
                'middle_name' => $attributes['middle_name'] ?? null,
                'family_name' => $attributes['family_name'],
                'preferred_name' => $attributes['preferred_name'] ?? null,
            ]);

            if (! $profile->isDirty()) {
                return $profile;
            }

            $changedFields = array_keys($profile->getDirty());
            $profile->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'identity.profile.updated',
                actor: $actor,
                targetType: 'person',
                targetId: $lockedPerson->public_id,
                metadata: ['changed_fields' => $changedFields],
            ));

            return $profile;
        }, attempts: 3);
    }
}
