<?php

namespace App\Support\Authorization;

use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssignRoleToUserAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        User $user,
        Role $role,
        ?User $actor = null,
        ?CarbonInterface $expiresAt = null,
    ): RoleAssignment {
        new AuthorizationCode($role->code);
        $assignedAt = now()->utc();

        if ($expiresAt !== null && $expiresAt->lessThanOrEqualTo($assignedAt)) {
            throw new InvalidArgumentException('Role assignment expiry must be in the future.');
        }

        return DB::transaction(function () use ($user, $role, $actor, $assignedAt, $expiresAt): RoleAssignment {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $lockedRole = Role::query()->lockForUpdate()->findOrFail($role->getKey());

            $activeAssignment = RoleAssignment::query()
                ->whereBelongsTo($lockedUser)
                ->whereBelongsTo($lockedRole)
                ->active($assignedAt)
                ->lockForUpdate()
                ->first();

            if ($activeAssignment !== null) {
                return $activeAssignment;
            }

            $roleAssignment = RoleAssignment::query()->create([
                'user_id' => $lockedUser->getKey(),
                'role_id' => $lockedRole->getKey(),
                'assigned_by_user_id' => $actor?->getKey(),
                'assigned_at' => $assignedAt,
                'expires_at' => $expiresAt,
                'revoked_at' => null,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'identity.role.assigned',
                actor: $actor,
                targetType: 'role_assignment',
                targetId: $roleAssignment->public_id,
                metadata: [
                    'role_code' => $lockedRole->code,
                    'user_id' => $lockedUser->getKey(),
                ],
            ));

            return $roleAssignment;
        }, attempts: 3);
    }
}
