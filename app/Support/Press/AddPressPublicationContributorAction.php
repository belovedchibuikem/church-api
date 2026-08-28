<?php

namespace App\Support\Press;

use App\Models\Person;
use App\Models\PressPublication;
use App\Models\PressPublicationContributor;
use App\Models\User;
use App\Press\PressContributorRole;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class AddPressPublicationContributorAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        PressPublication $publication,
        Person $person,
        PressContributorRole $role,
        User $actor,
    ): PressPublicationContributor {
        return DB::transaction(function () use ($publication, $person, $role, $actor): PressPublicationContributor {
            $lockedPublication = PressPublication::query()->lockForUpdate()->findOrFail($publication->getKey());
            $lockedPerson = Person::query()->lockForUpdate()->findOrFail($person->getKey());
            $existing = PressPublicationContributor::query()
                ->where('press_publication_id', $lockedPublication->getKey())
                ->where('person_id', $lockedPerson->getKey())
                ->where('role', $role->value)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $contributor = new PressPublicationContributor;
            $contributor->forceFill([
                'press_publication_id' => $lockedPublication->getKey(),
                'person_id' => $lockedPerson->getKey(),
                'role' => $role,
            ]);
            $contributor->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'press.publication.contributor_added',
                actor: $actor,
                targetType: 'press_publication',
                targetId: $lockedPublication->public_id,
                scopeType: 'press_publication',
                scopeId: $lockedPublication->public_id,
                metadata: ['role' => $role->value],
            ));

            return $contributor;
        }, attempts: 3);
    }
}
