<?php

namespace App\Support\Church;

use App\Models\Church;
use App\Models\Person;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Authorization\AuthorizationBundleCatalog;
use Illuminate\Support\Facades\DB;

class RevokeChurchScopedAdminAccessAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(Person $person, Church $church, ?User $actor = null): void
    {
        DB::transaction(function () use ($person, $church, $actor): void {
            $lockedPerson = Person::query()->with('user')->lockForUpdate()->findOrFail($person->getKey());
            $user = $lockedPerson->user;
            if ($user === null) {
                return;
            }

            $assignments = RoleAssignment::query()
                ->with(['role:id,code', 'scopeAssignments:id,role_assignment_id,scope_type,scope_key'])
                ->whereBelongsTo($user)
                ->active()
                ->whereHas('role', fn ($query) => $query->where(
                    'code',
                    AuthorizationBundleCatalog::CHURCH_OPERATIONS_ADMINISTRATOR_ROLE,
                ))
                ->whereHas('scopeAssignments', fn ($query) => $query
                    ->where('scope_type', 'church')
                    ->where('scope_key', $church->public_id))
                ->lockForUpdate()
                ->get();

            $now = now()->utc();
            foreach ($assignments as $assignment) {
                if ($assignment->revoked_at !== null) {
                    continue;
                }

                $assignment->revoked_at = $now;
                $assignment->save();

                $this->recordAuditEvent->handle(new AuditEventData(
                    action: 'church.leadership.admin_access_revoked',
                    actor: $actor,
                    targetType: 'role_assignment',
                    targetId: $assignment->public_id,
                    scopeType: 'church',
                    scopeId: $church->public_id,
                    metadata: ['person_id' => $lockedPerson->public_id],
                ));
            }
        }, attempts: 3);
    }
}
