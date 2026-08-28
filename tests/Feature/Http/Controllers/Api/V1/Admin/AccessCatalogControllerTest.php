<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Admin;

use App\Models\AccessDecision;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AccessCatalogControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_returns_400_when_scope_headers_are_missing(): void
    {
        $actor = User::factory()->create();
        $this->authenticate($actor);

        $this->getJson('/api/v1/admin/access/roles')
            ->assertBadRequest()
            ->assertJsonPath('error.code', 'AUTH_SCOPE_REQUIRED');
    }

    public function test_returns_403_and_records_decision_when_permission_is_missing(): void
    {
        $actor = User::factory()->create();
        $this->authenticate($actor);

        $this->withHeaders($this->globalScopeHeaders())
            ->getJson('/api/v1/admin/access/permissions')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AUTH_PERMISSION_DENIED');

        $this->assertSame(1, AccessDecision::query()->where('allowed', false)->count());
    }

    public function test_lists_minimized_role_catalog_for_authorized_actor(): void
    {
        $actor = $this->actorWithPermission('identity.roles.view');
        $catalogRole = Role::factory()->create(['code' => 'country_reviewer', 'name' => 'Country reviewer']);
        $catalogPermission = Permission::factory()->create(['code' => 'reports.view']);
        $this->app->make(GrantPermissionToRoleAction::class)->handle($catalogRole, $catalogPermission);
        $this->authenticate($actor);

        $this->withHeaders($this->globalScopeHeaders())
            ->getJson('/api/v1/admin/access/roles?filter[search]=country_reviewer')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'country_reviewer')
            ->assertJsonPath('data.0.permissions.0.code', 'reports.view')
            ->assertJsonMissingPath('data.0.created_at')
            ->assertJsonStructure(['meta' => ['pagination' => ['current_page', 'per_page', 'last_page', 'total']]]);
    }

    private function actorWithPermission(string $permissionCode): User
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create(['code' => $permissionCode]);
        $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle(
            $assignment,
            new ScopeReference('global', 'platform'),
        );

        return $actor;
    }

    private function authenticate(User $user): void
    {
        $securitySession = SecuritySession::factory()->for($user)->create();
        $this->actingAs($user);
        $this->withSession([
            'security_session_id' => $securitySession->public_id,
            'auth.mfa_verified_at' => now()->utc()->toIso8601String(),
        ]);
    }

    /** @return array<string, string> */
    private function globalScopeHeaders(): array
    {
        return ['X-Scope-Type' => 'global', 'X-Scope-ID' => 'platform'];
    }
}
