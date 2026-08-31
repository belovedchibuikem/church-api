<?php

namespace App\Support\Press;

use App\Models\Person;
use App\Models\PressAuthor;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpsertPressAuthorAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(Person $person, string $displayName, User $actor, ?string $bio = null): PressAuthor
    {
        $displayName = trim($displayName);
        if ($displayName === '') {
            throw new DomainException('Author display name is required.');
        }

        return DB::transaction(function () use ($person, $displayName, $actor, $bio): PressAuthor {
            $lockedPerson = Person::query()->lockForUpdate()->findOrFail($person->getKey());
            $author = PressAuthor::query()->where('person_id', $lockedPerson->getKey())->lockForUpdate()->first();

            if ($author === null) {
                $author = new PressAuthor;
                $author->forceFill([
                    'person_id' => $lockedPerson->getKey(),
                    'display_name' => $displayName,
                    'bio' => $bio,
                    'status' => 'active',
                ]);
                $author->save();
                $action = 'press.author.created';
            } else {
                if ($author->status === 'archived') {
                    throw new DomainException('Archived authors cannot be updated. Restore or merge first.');
                }
                $author->forceFill([
                    'display_name' => $displayName,
                    'bio' => $bio,
                ]);
                $author->save();
                $action = 'press.author.updated';
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: $action,
                actor: $actor,
                targetType: 'press_author',
                targetId: $author->public_id,
                scopeType: 'press_author',
                scopeId: $author->public_id,
                metadata: ['person_id' => $lockedPerson->public_id],
            ));

            return $author;
        }, attempts: 3);
    }
}
