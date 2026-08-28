<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Admin;

use App\Identity\UserAccountStatus;
use App\Models\AccessDecision;
use App\Models\AuditEvent;
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

class UserAdministrationControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_returns_401_when_authentication_is_missing(): void
    {
        $this->withHeaders($this->globalScopeHeaders())
            ->getJson('/api/v1/admin/users')
            ->assertUnauthorized();
    }

    public function test_lists_users_with_opaque_ids_for_global_authority(): void
    {
        $actor = $this->actorWithPermissionAtScope('identity.users.view', new ScopeReference('global', 'platform'));
        $target = User::factory()->create(['email' => 'member@example.test']);
        $this->authenticate($actor);

        $this->withHeaders($this->globalScopeHeaders())
            ->getJson('/api/v1/admin/users?filter[search]=member%40example.test')
            ->assertOk()
            ->assertJsonPath('data.0.id', $target->public_id)
            ->assertJsonPath('data.0.email', 'member@example.test')
            ->assertJsonMissing(['id' => $target->getKey()]);

        $this->assertSame(1, AccessDecision::query()->where('allowed', true)->count());
    }

    public function test_returns_404_for_user_outside_authorized_exact_scope(): void
    {
        $visibleScope = new ScopeReference('church', '01JCHURCH00000000000000001');
        $actor = $this->actorWithPermissionAtScope('identity.users.view', $visibleScope);
        $hiddenUser = User::factory()->create();
        $this->assignScope($hiddenUser, new ScopeReference('church', '01JCHURCH00000000000000002'));
        $this->authenticate($actor);

        $this->withHeaders(['X-Scope-Type' => $visibleScope->type, 'X-Scope-ID' => $visibleScope->key])
            ->getJson("/api/v1/admin/users/{$hiddenUser->public_id}")
            ->assertNotFound();
    }

    public function test_suspends_scoped_user_and_records_audit_event(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissionAtScope('identity.users.suspend', $scope);
        $target = User::factory()->create();
        $this->authenticate($actor);

        $this->withHeaders($this->globalScopeHeaders())
            ->postJson("/api/v1/admin/users/{$target->public_id}/suspension", ['reason' => 'security.review'])
            ->assertOk()
            ->assertJsonPath('data.account_status', UserAccountStatus::Suspended->value)
            ->assertJsonPath('data.suspension_reason', 'security.review');

        $this->assertSame(UserAccountStatus::Suspended, $target->fresh()->account_status);
        $this->assertTrue(AuditEvent::query()->where('action', 'identity.user.suspended')->exists());
    }

    public function test_returns_403_when_administrator_attempts_self_suspension(): void
    {
        $actor = $this->actorWithPermissionAtScope('identity.users.suspend', new ScopeReference('global', 'platform'));
        $this->authenticate($actor);

        $this->withHeaders($this->globalScopeHeaders())
            ->postJson("/api/v1/admin/users/{$actor->public_id}/suspension", ['reason' => 'security.review'])
            ->assertForbidden();

        $this->assertSame(UserAccountStatus::Active, $actor->fresh()->account_status);
        $this->assertFalse(AuditEvent::query()->where('action', 'identity.user.suspended')->exists());
    }

    private function actorWithPermissionAtScope(string $permissionCode, ScopeReference $scope): User
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create(['code' => $permissionCode]);
        $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle($assignment, $scope);

        return $actor;
    }

    private function assignScope(User $user, ScopeReference $scope): void
    {
        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($user, Role::factory()->create());
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle($assignment, $scope);
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
