<?php

namespace App\Support\Authorization;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class ProvisionAuthorizationBundlesAction
{
    public function __construct(
        private readonly AuthorizationBundleCatalog $catalog,
        private readonly GrantPermissionToRoleAction $grantPermission,
    ) {}

    /** @return array{roles: int, permissions: int, grants: int} */
    public function handle(): array
    {
        return DB::transaction(function (): array {
            $permissions = collect($this->catalog->permissionCodes())
                ->mapWithKeys(fn (string $code): array => [
                    $code => Permission::query()->firstOrCreate(['code' => $code]),
                ]);
            $roles = 0;
            $grants = 0;

            foreach (AuthorizationBundleCatalog::BUNDLES as $code => $bundle) {
                $role = Role::query()->firstOrCreate(
                    ['code' => $code],
                    ['name' => $bundle['name']],
                );
                $roles++;

                foreach ($bundle['permissions'] as $permissionCode) {
                    $grant = $this->grantPermission->handle($role, $permissions->get($permissionCode));
                    $grants += $grant->wasRecentlyCreated ? 1 : 0;
                }
            }

            $superRole = Role::query()->firstOrCreate(
                ['code' => AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE],
                ['name' => 'Super administrator'],
            );
            $roles++;

            foreach ($this->catalog->permissionCodes() as $permissionCode) {
                $grant = $this->grantPermission->handle($superRole, $permissions->get($permissionCode));
                $grants += $grant->wasRecentlyCreated ? 1 : 0;
            }

            return [
                'roles' => $roles,
                'permissions' => $permissions->count(),
                'grants' => $grants,
            ];
        }, attempts: 3);
    }
}
