<?php

namespace App\Support\People;

use App\Models\ChurchMembership;
use App\Models\ChurchRoleAssignment;
use App\Models\Convert;
use App\Models\CounsellingCase;
use App\Models\FirstTimer;
use App\Models\FollowUpTask;
use App\Models\PastoralNeed;
use App\Models\Person;
use App\Models\PrayerRequest;
use App\Models\Testimony;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class MergePeopleAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(Person $canonical, Person $duplicate, User $actor): Person
    {
        if ($canonical->is($duplicate)) {
            throw new InvalidArgumentException('Cannot merge a person into themselves.');
        }
        if ($canonical->archived_at !== null || $duplicate->archived_at !== null) {
            throw new InvalidArgumentException('Archived people cannot be merged.');
        }
        if ($canonical->user !== null && $duplicate->user !== null) {
            throw new InvalidArgumentException('Both people have login accounts. Unlink one user before merging.');
        }

        return DB::transaction(function () use ($canonical, $duplicate, $actor): Person {
            $lockedCanonical = Person::query()->lockForUpdate()->findOrFail($canonical->getKey());
            $lockedDuplicate = Person::query()->lockForUpdate()->findOrFail($duplicate->getKey());

            $this->repoint(ChurchMembership::class, 'person_id', $lockedDuplicate->getKey(), $lockedCanonical->getKey());
            $this->repoint(FirstTimer::class, 'person_id', $lockedDuplicate->getKey(), $lockedCanonical->getKey());
            $this->repoint(Convert::class, 'person_id', $lockedDuplicate->getKey(), $lockedCanonical->getKey());
            $this->repoint(ChurchRoleAssignment::class, 'person_id', $lockedDuplicate->getKey(), $lockedCanonical->getKey());
            $this->repoint(PrayerRequest::class, 'person_id', $lockedDuplicate->getKey(), $lockedCanonical->getKey());
            $this->repoint(PrayerRequest::class, 'assigned_to_person_id', $lockedDuplicate->getKey(), $lockedCanonical->getKey());
            $this->repoint(PastoralNeed::class, 'person_id', $lockedDuplicate->getKey(), $lockedCanonical->getKey());
            $this->repoint(Testimony::class, 'person_id', $lockedDuplicate->getKey(), $lockedCanonical->getKey());
            $this->repoint(FollowUpTask::class, 'assigned_to_person_id', $lockedDuplicate->getKey(), $lockedCanonical->getKey());
            $this->repoint(CounsellingCase::class, 'client_person_id', $lockedDuplicate->getKey(), $lockedCanonical->getKey());
            $this->repoint(CounsellingCase::class, 'counselor_person_id', $lockedDuplicate->getKey(), $lockedCanonical->getKey());

            if ($lockedDuplicate->user !== null && $lockedCanonical->user === null) {
                $lockedDuplicate->user->forceFill(['person_id' => $lockedCanonical->getKey()])->save();
            }

            $lockedDuplicate->forceFill([
                'archived_at' => now()->utc(),
                'merged_into_person_id' => $lockedCanonical->getKey(),
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'people.merged',
                actor: $actor,
                targetType: 'person',
                targetId: $lockedCanonical->public_id,
                metadata: [
                    'source_person_id' => $lockedDuplicate->public_id,
                ],
            ));

            return $lockedCanonical->fresh(['profile', 'user']) ?? $lockedCanonical;
        }, attempts: 3);
    }

    /**
     * @param  class-string  $model
     */
    private function repoint(string $model, string $column, int $from, int $to): void
    {
        $model::query()->where($column, $from)->get()->each(function ($record) use ($column, $to): void {
            try {
                $record->forceFill([$column => $to])->save();
            } catch (Throwable) {
                // Unique collisions keep the source row as historical evidence.
            }
        });
    }
}
