<?php

namespace App\Support\Authorization;

use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\ScopeAssignment;
use App\Models\User;
use App\Support\Authorization\Contracts\ScopeContainmentResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuthorizationDecisionService
{
    public function __construct(
        private ScopeContainmentResolver $scopeContainmentResolver,
        private RecordAccessDecisionAction $recordAccessDecision,
    ) {}

    public function decide(
        User $actor,
        string $permissionCode,
        ScopeReference $requestedScope,
    ): AccessDecisionResult {
        $permission = new AuthorizationCode($permissionCode);

        return DB::transaction(function () use ($actor, $permission, $requestedScope): AccessDecisionResult {
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $decidedAt = now()->utc();

            if ($lockedActor->isSuspended()) {
                return $this->recordResult(
                    actor: $lockedActor,
                    permission: $permission,
                    requestedScope: $requestedScope,
                    reason: AccessDecisionReason::AccountSuspended,
                );
            }

            $superAdministratorAssignment = $this->superAdministratorAssignment(
                $lockedActor,
                $requestedScope,
                $decidedAt,
            );

            if ($superAdministratorAssignment !== null) {
                return $this->recordResult(
                    actor: $lockedActor,
                    permission: $permission,
                    requestedScope: $requestedScope,
                    reason: AccessDecisionReason::Allowed,
                    matchedRoleAssignment: $superAdministratorAssignment,
                );
            }

            $roleAssignments = RoleAssignment::query()
                ->select(['id', 'public_id', 'user_id', 'role_id'])
                ->whereBelongsTo($lockedActor)
                ->active($decidedAt)
                ->whereHas(
                    'role.rolePermissions.permission',
                    function (Builder $query) use ($permission): void {
                        $query->where('code', $permission->value);
                    },
                )
                ->lockForUpdate()
                ->get();

            if ($roleAssignments->isEmpty()) {
                return $this->recordResult(
                    actor: $lockedActor,
                    permission: $permission,
                    requestedScope: $requestedScope,
                    reason: AccessDecisionReason::PermissionNotAssigned,
                );
            }

            /** @var Collection<int, Collection<int, ScopeAssignment>> $scopeAssignmentsByRole */
            $scopeAssignmentsByRole = ScopeAssignment::query()
                ->select(['id', 'role_assignment_id', 'scope_type', 'scope_key'])
                ->whereIn('role_assignment_id', $roleAssignments->modelKeys())
                ->lockForUpdate()
                ->get()
                ->groupBy('role_assignment_id');
            $hasScopeAssignment = false;

            foreach ($roleAssignments as $roleAssignment) {
                $scopeAssignments = $scopeAssignmentsByRole->get($roleAssignment->getKey(), collect());

                foreach ($scopeAssignments as $scopeAssignment) {
                    $hasScopeAssignment = true;
                    $assignedScope = ScopeReference::fromAssignment($scopeAssignment);

                    if ($this->scopeContainmentResolver->contains($assignedScope, $requestedScope, $lockedActor)) {
                        return $this->recordResult(
                            actor: $lockedActor,
                            permission: $permission,
                            requestedScope: $requestedScope,
                            reason: AccessDecisionReason::Allowed,
                            matchedRoleAssignment: $roleAssignment,
                        );
                    }
                }
            }

            return $this->recordResult(
                actor: $lockedActor,
                permission: $permission,
                requestedScope: $requestedScope,
                reason: $hasScopeAssignment
                    ? AccessDecisionReason::ScopeNotContained
                    : AccessDecisionReason::ScopeNotAssigned,
            );
        }, attempts: 3);
    }

    private function superAdministratorAssignment(
        User $actor,
        ScopeReference $requestedScope,
        \DateTimeInterface $decidedAt,
    ): ?RoleAssignment {
        $superRoleId = Role::query()
            ->where('code', AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE)
            ->value('id');

        if ($superRoleId === null) {
            return null;
        }

        $assignments = RoleAssignment::query()
            ->select(['id', 'public_id', 'user_id', 'role_id'])
            ->whereBelongsTo($actor)
            ->where('role_id', $superRoleId)
            ->active($decidedAt)
            ->with(['scopeAssignments:id,role_assignment_id,scope_type,scope_key'])
            ->get();

        foreach ($assignments as $assignment) {
            foreach ($assignment->scopeAssignments as $scopeAssignment) {
                $assignedScope = ScopeReference::fromAssignment($scopeAssignment);

                if ($this->scopeContainmentResolver->contains($assignedScope, $requestedScope, $actor)) {
                    return $assignment;
                }
            }
        }

        return null;
    }

    private function recordResult(
        User $actor,
        AuthorizationCode $permission,
        ScopeReference $requestedScope,
        AccessDecisionReason $reason,
        ?RoleAssignment $matchedRoleAssignment = null,
    ): AccessDecisionResult {
        $record = $this->recordAccessDecision->handle(
            actor: $actor,
            permission: $permission,
            scope: $requestedScope,
            reason: $reason,
            matchedRoleAssignment: $matchedRoleAssignment,
        );

        return new AccessDecisionResult(
            allowed: $record->allowed,
            reason: $reason,
            record: $record,
        );
    }
}
