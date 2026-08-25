<?php

namespace App\Support\Identity;

use App\Exceptions\IdentityLinkConflictException;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class LinkUserToPersonAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(User $user, Person $person, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $person, $actor): User {
            $lockedPerson = Person::query()->lockForUpdate()->findOrFail($person->getKey());
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->person_id === $lockedPerson->getKey()) {
                return $lockedUser;
            }

            if ($lockedUser->person_id !== null) {
                throw new IdentityLinkConflictException;
            }

            $existingUser = User::query()
                ->whereBelongsTo($lockedPerson)
                ->lockForUpdate()
                ->first();

            if ($existingUser !== null) {
                throw new IdentityLinkConflictException;
            }

            $lockedUser->person()->associate($lockedPerson);
            $lockedUser->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'identity.person.user_linked',
                actor: $actor,
                targetType: 'person',
                targetId: $lockedPerson->public_id,
                metadata: ['user_id' => $lockedUser->getKey()],
            ));

            return $lockedUser;
        }, attempts: 3);
    }
}
