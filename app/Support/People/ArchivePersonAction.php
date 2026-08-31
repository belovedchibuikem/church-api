<?php

namespace App\Support\People;

use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use InvalidArgumentException;

class ArchivePersonAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(Person $person, User $actor, string $reason): Person
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A reason is required to archive a person.');
        }
        if ($person->archived_at !== null) {
            throw new InvalidArgumentException('This person is already archived.');
        }

        $person->forceFill(['archived_at' => now()->utc()])->save();

        $this->recordAuditEvent->handle(new AuditEventData(
            action: 'people.archived',
            actor: $actor,
            targetType: 'person',
            targetId: $person->public_id,
            metadata: ['reason' => $reason],
        ));

        return $person->fresh(['profile', 'user']) ?? $person;
    }
}
