<?php

namespace Tests\Feature\Support\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\RolePermission;
use App\Support\Authorization\AuthorizationBundleCatalog;
use App\Support\Authorization\ProvisionAuthorizationBundlesAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationBundleCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisions_only_explicit_stable_bundles_without_assigning_users(): void
    {
        $result = $this->app->make(ProvisionAuthorizationBundlesAction::class)->handle();

        $this->assertSame(9, $result['roles']);
        $this->assertSame(124, $result['permissions']);
        $this->assertSame(248, $result['grants']);
        $this->assertSame([
            AuthorizationBundleCatalog::CHURCH_OPERATIONS_ADMINISTRATOR_ROLE,
            AuthorizationBundleCatalog::DOMAIN_CATALOG_ADMINISTRATOR_ROLE,
            AuthorizationBundleCatalog::DOMAIN_OPERATIONS_ADMINISTRATOR_ROLE,
            AuthorizationBundleCatalog::MEMBER_SECURITY_ROLE,
            AuthorizationBundleCatalog::MISSION_OPERATIONS_ADMINISTRATOR_ROLE,
            AuthorizationBundleCatalog::ORGANIZATION_ADMINISTRATOR_ROLE,
            AuthorizationBundleCatalog::PLATFORM_ADMINISTRATOR_ROLE,
            AuthorizationBundleCatalog::PLATFORM_SETTINGS_ADMINISTRATOR_ROLE,
            AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE,
        ], Role::query()->orderBy('code')->pluck('code')->all());
        $this->assertSame(124, Permission::query()->count());
        $this->assertSame(248, RolePermission::query()->count());
        $this->assertSame(0, RoleAssignment::query()->count());
        $this->assertFalse(Permission::query()->where('code', 'like', '%*%')->exists());
    }

    public function test_repeated_provisioning_is_idempotent(): void
    {
        $action = $this->app->make(ProvisionAuthorizationBundlesAction::class);

        $action->handle();
        $result = $action->handle();

        $this->assertSame(0, $result['grants']);
        $this->assertSame(9, Role::query()->count());
        $this->assertSame(124, Permission::query()->count());
        $this->assertSame(248, RolePermission::query()->count());
    }
}
