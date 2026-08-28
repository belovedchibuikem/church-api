<?php

namespace App\Support\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class GrantPermissionToRoleAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(Role $role, Permission $permission, ?User $actor = null): RolePermission
    {
        new AuthorizationCode($role->code);
        new AuthorizationCode($permission->code);

        return DB::transaction(function () use ($role, $permission, $actor): RolePermission {
            $lockedRole = Role::query()->lockForUpdate()->findOrFail($role->getKey());
            $lockedPermission = Permission::query()->lockForUpdate()->findOrFail($permission->getKey());

            $rolePermission = RolePermission::query()->firstOrCreate(
                [
                    'role_id' => $lockedRole->getKey(),
                    'permission_id' => $lockedPermission->getKey(),
                ],
                ['granted_by_user_id' => $actor?->getKey()],
            );

            if ($rolePermission->wasRecentlyCreated) {
                $this->recordAuditEvent->handle(new AuditEventData(
                    action: 'identity.role.permission_granted',
                    actor: $actor,
                    targetType: 'role_permission',
                    targetId: $rolePermission->public_id,
                    metadata: [
                        'role_code' => $lockedRole->code,
                        'permission_code' => $lockedPermission->code,
                    ],
                ));
            }

            return $rolePermission;
        }, attempts: 3);
    }
}
