<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\MobilePermissionAliasCatalog;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserAuthorizationApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_authenticated_user_receives_capability_snapshot(): void
    {
        $actor = $this->memberWithMobileAccess();
        $this->authenticateBrowser($actor);

        $this->getJson('/api/v1/user/capabilities')
            ->assertOk()
            ->assertJsonPath('data.permissions.0', MobilePermissionAliasCatalog::MOBILE_APP_ACCESS)
            ->assertJsonPath('data.scopes.0.type', 'global')
            ->assertJsonPath('data.scopes.0.key', 'platform');
    }

    public function test_authorization_check_allows_aliased_mobile_permission(): void
    {
        $actor = $this->memberWithMobileAccess();
        $this->authenticateBrowser($actor);

        $this->postJson('/api/v1/user/authorization/check', [
            'permission' => 'church.dashboard.view',
        ])
            ->assertOk()
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.state', 'allowed')
            ->assertJsonPath('data.canonical_permission', MobilePermissionAliasCatalog::MOBILE_APP_ACCESS);
    }

    public function test_giving_history_uses_member_mobile_access(): void
    {
        $actor = $this->memberWithMobileAccess();
        $this->authenticateBrowser($actor);

        $this->postJson('/api/v1/user/authorization/check', [
            'permission' => 'giving.history.view',
        ])
            ->assertOk()
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.canonical_permission', MobilePermissionAliasCatalog::MOBILE_APP_ACCESS);
    }

    public function test_authorization_check_forbids_missing_capability(): void
    {
        $actor = User::factory()->create();
        $this->authenticateBrowser($actor);

        $this->postJson('/api/v1/user/authorization/check', [
            'permission' => 'church.dashboard.view',
        ])
            ->assertOk()
            ->assertJsonPath('data.allowed', false)
            ->assertJsonPath('data.state', 'forbidden')
            ->assertJsonPath('data.reason', 'permission_not_assigned');
    }

    private function memberWithMobileAccess(): User
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create(['code' => 'member_mobile_test']);
        $permission = Permission::factory()->create([
            'code' => MobilePermissionAliasCatalog::MOBILE_APP_ACCESS,
        ]);
        $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle(
            $assignment,
            new ScopeReference('global', 'platform'),
        );

        return $actor;
    }

    private function authenticateBrowser(User $user): void
    {
        $securitySession = SecuritySession::factory()->for($user)->create();
        $this->actingAs($user);
        $this->withSession([
            'security_session_id' => $securitySession->public_id,
            'auth.mfa_verified_at' => now()->utc()->toIso8601String(),
        ]);
    }
}
