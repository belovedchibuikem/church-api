<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\ScopeAssignment;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ScopeAssignmentControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_returns_only_assignments_contained_by_requested_scope(): void
    {
        $visibleScopeId = '01JCHURCH00000000000000001';
        $actor = $this->actorWithPermissionAtScope('identity.scopes.view', new ScopeReference('church', $visibleScopeId));
        $this->assignScopeToNewUser(new ScopeReference('church', $visibleScopeId));
        $hidden = $this->assignScopeToNewUser(new ScopeReference('church', '01JCHURCH00000000000000002'));
        $this->authenticate($actor);

        $this->withHeaders(['X-Scope-Type' => 'church', 'X-Scope-ID' => $visibleScopeId])
            ->getJson('/api/v1/admin/access/scope-assignments')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['id' => $hidden->public_id]);
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

    private function assignScopeToNewUser(ScopeReference $scope): ScopeAssignment
    {
        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle(
            User::factory()->create(),
            Role::factory()->create(),
        );

        return $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle($assignment, $scope);
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
}
