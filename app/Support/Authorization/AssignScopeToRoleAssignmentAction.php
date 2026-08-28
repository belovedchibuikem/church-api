<?php

namespace App\Support\Authorization;

use App\Models\RoleAssignment;
use App\Models\ScopeAssignment;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssignScopeToRoleAssignmentAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        RoleAssignment $roleAssignment,
        ScopeReference $scope,
        ?User $actor = null,
    ): ScopeAssignment {
        return DB::transaction(function () use ($roleAssignment, $scope, $actor): ScopeAssignment {
            $lockedAssignment = RoleAssignment::query()
                ->lockForUpdate()
                ->findOrFail($roleAssignment->getKey());
            $now = now()->utc();

            if (
                $lockedAssignment->revoked_at !== null
                || $lockedAssignment->assigned_at->greaterThan($now)
                || ($lockedAssignment->expires_at !== null && $lockedAssignment->expires_at->lessThanOrEqualTo($now))
            ) {
                throw new InvalidArgumentException('Scopes can only be attached to active role assignments.');
            }

            $scopeAssignment = ScopeAssignment::query()->firstOrCreate(
                [
                    'role_assignment_id' => $lockedAssignment->getKey(),
                    'scope_type' => $scope->type,
                    'scope_key' => $scope->key,
                ],
                ['assigned_by_user_id' => $actor?->getKey()],
            );

            if ($scopeAssignment->wasRecentlyCreated) {
                $this->recordAuditEvent->handle(new AuditEventData(
                    action: 'organization.scope.assigned',
                    actor: $actor,
                    targetType: 'scope_assignment',
                    targetId: $scopeAssignment->public_id,
                    scopeType: $scope->type,
                    scopeId: $scope->key,
                    metadata: ['role_assignment_id' => $lockedAssignment->public_id],
                ));
            }

            return $scopeAssignment;
        }, attempts: 3);
    }
}
