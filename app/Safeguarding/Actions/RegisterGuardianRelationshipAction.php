<?php

namespace App\Safeguarding\Actions;

use App\Models\GuardianRelationship;
use App\Models\Person;
use App\Models\User;
use App\Safeguarding\GuardianRelationshipStatus;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class RegisterGuardianRelationshipAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(Person $guardian, Person $child, string $relationshipType, ?User $actor = null): GuardianRelationship
    {
        if ($guardian->is($child)) {
            throw new InvalidArgumentException('A person cannot be their own guardian.');
        }

        if (! Str::isMatch('/\A[a-z][a-z0-9_-]{1,49}\z/', $relationshipType)) {
            throw new InvalidArgumentException('Guardian relationship type must be a stable code.');
        }

        return DB::transaction(function () use ($guardian, $child, $relationshipType, $actor): GuardianRelationship {
            Person::query()->whereKey([$guardian->getKey(), $child->getKey()])->lockForUpdate()->get();

            $existing = GuardianRelationship::query()
                ->whereBelongsTo($guardian, 'guardian')
                ->whereBelongsTo($child, 'child')
                ->where('relationship_type', $relationshipType)
                ->whereIn('status', [GuardianRelationshipStatus::Pending, GuardianRelationshipStatus::Verified])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $relationship = new GuardianRelationship(['relationship_type' => $relationshipType]);
            $relationship->guardian()->associate($guardian);
            $relationship->child()->associate($child);
            $relationship->status = GuardianRelationshipStatus::Pending;
            $relationship->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'safeguarding.guardian_relationship.registered',
                actor: $actor,
                targetType: 'guardian_relationship',
                targetId: $relationship->public_id,
                metadata: ['status' => $relationship->status->value],
            ));

            return $relationship;
        }, attempts: 3);
    }
}
