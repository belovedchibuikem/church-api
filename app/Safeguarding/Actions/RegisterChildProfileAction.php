<?php

namespace App\Safeguarding\Actions;

use App\Models\ChildProfile;
use App\Models\Person;
use App\Models\User;
use App\Safeguarding\MinorStatus;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

final readonly class RegisterChildProfileAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        Person $person,
        ?string $dateOfBirth,
        MinorStatus $minorStatus,
        bool $directCommunicationRestricted,
        bool $mediaUseRestricted,
        ?User $actor = null,
    ): ChildProfile {
        return DB::transaction(function () use ($person, $dateOfBirth, $minorStatus, $directCommunicationRestricted, $mediaUseRestricted, $actor): ChildProfile {
            Person::query()->whereKey($person->getKey())->lockForUpdate()->firstOrFail();

            $profile = ChildProfile::query()
                ->whereBelongsTo($person)
                ->lockForUpdate()
                ->first();

            $created = $profile === null;
            if ($profile === null) {
                $profile = new ChildProfile;
                $profile->person()->associate($person);
            }

            if ($dateOfBirth !== null && $dateOfBirth !== '') {
                $profile->date_of_birth = $dateOfBirth;
            }
            $profile->minor_status = $minorStatus;
            $profile->direct_communication_restricted = $directCommunicationRestricted;
            $profile->media_use_restricted = $mediaUseRestricted;
            $profile->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: $created ? 'safeguarding.child_profile.registered' : 'safeguarding.child_profile.updated',
                actor: $actor,
                targetType: 'child_profile',
                targetId: $profile->public_id,
                metadata: [
                    'minor_status' => $minorStatus->value,
                    'direct_communication_restricted' => $directCommunicationRestricted,
                    'media_use_restricted' => $mediaUseRestricted,
                ],
            ));

            return $profile;
        }, attempts: 3);
    }
}
