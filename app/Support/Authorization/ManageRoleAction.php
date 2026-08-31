<?php

namespace App\Support\Authorization;

use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ManageRoleAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function create(string $code, string $name, User $actor): Role
    {
        $authorizationCode = new AuthorizationCode($code);
        $normalizedName = Str::squish($name);
        if ($normalizedName === '' || Str::length($normalizedName) > 191) {
            throw new InvalidArgumentException('Role names must contain between 1 and 191 characters.');
        }
        if ($this->isSystemCode($authorizationCode->value)) {
            throw new ConflictHttpException('System authorization bundles cannot be recreated.');
        }

        return DB::transaction(function () use ($authorizationCode, $normalizedName, $actor): Role {
            $existing = Role::query()->where('code', $authorizationCode->value)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->name !== $normalizedName) {
                    throw new ConflictHttpException('A role with this code already exists.');
                }

                return $existing;
            }

            $role = Role::query()->create([
                'code' => $authorizationCode->value,
                'name' => $normalizedName,
            ]);
            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'identity.role.created',
                actor: $actor,
                targetType: 'role',
                targetId: $role->public_id,
                metadata: ['code' => $role->code],
            ));

            return $role;
        }, attempts: 3);
    }

    public function update(Role $role, string $name, User $actor): Role
    {
        $normalizedName = Str::squish($name);
        if ($normalizedName === '' || Str::length($normalizedName) > 191) {
            throw new InvalidArgumentException('Role names must contain between 1 and 191 characters.');
        }

        return DB::transaction(function () use ($role, $normalizedName, $actor): Role {
            $locked = Role::query()->lockForUpdate()->findOrFail($role->getKey());
            if ($locked->name === $normalizedName) {
                return $locked;
            }
            $locked->name = $normalizedName;
            $locked->save();
            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'identity.role.updated',
                actor: $actor,
                targetType: 'role',
                targetId: $locked->public_id,
                metadata: ['changed_fields' => ['name']],
            ));

            return $locked;
        }, attempts: 3);
    }

    public function archive(Role $role, User $actor): void
    {
        if ($this->isSystemCode((string) $role->code)) {
            throw new ConflictHttpException('System roles are retained and cannot be deleted.');
        }

        DB::transaction(function () use ($role, $actor): void {
            $locked = Role::query()->lockForUpdate()->findOrFail($role->getKey());
            if ($locked->assignments()->exists()) {
                throw new ConflictHttpException('Roles with assignments cannot be deleted. Reassign users first.');
            }
            $publicId = $locked->public_id;
            $code = $locked->code;
            $locked->rolePermissions()->delete();
            $locked->delete();
            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'identity.role.archived',
                actor: $actor,
                targetType: 'role',
                targetId: $publicId,
                metadata: ['code' => $code],
            ));
        }, attempts: 3);
    }

    public function isSystemCode(string $code): bool
    {
        return $code === AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE
            || array_key_exists($code, AuthorizationBundleCatalog::BUNDLES);
    }
}
