<?php

namespace App\Support\Authorization;

use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RevokeRoleFromUserAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(User $actor, User $target, RoleAssignment $assignment): RoleAssignment
    {
        return DB::transaction(function () use ($actor, $target, $assignment): RoleAssignment {
            $locked = RoleAssignment::query()
                ->with('role:id,code,name,public_id')
                ->lockForUpdate()
                ->findOrFail($assignment->getKey());

            if ((int) $locked->user_id !== (int) $target->getKey()) {
                throw new InvalidArgumentException('Role assignment does not belong to this user.');
            }

            if ($locked->revoked_at !== null) {
                throw new InvalidArgumentException('Role assignment is already revoked.');
            }

            $now = now()->utc();
            if ($locked->expires_at !== null && $locked->expires_at->lessThanOrEqualTo($now)) {
                throw new InvalidArgumentException('Role assignment has already expired.');
            }

            $roleCode = (string) $locked->role?->code;
            if ($roleCode === AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE) {
                $otherSuperExists = RoleAssignment::query()
                    ->active($now)
                    ->whereKeyNot($locked->getKey())
                    ->whereHas('role', fn ($query) => $query->where('code', AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE))
                    ->exists();

                if (! $otherSuperExists) {
                    throw new InvalidArgumentException('Cannot revoke the last super-administrator assignment.');
                }

                if ((int) $actor->getKey() === (int) $target->getKey()) {
                    throw new AccessDeniedHttpException('You cannot revoke your own super-administrator role.');
                }
            }

            $locked->revoked_at = $now;
            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'identity.role.revoked',
                actor: $actor,
                targetType: 'role_assignment',
                targetId: $locked->public_id,
                metadata: [
                    'role_code' => $roleCode,
                    'user_id' => $target->getKey(),
                ],
            ));

            return $locked;
        }, attempts: 3);
    }
}
