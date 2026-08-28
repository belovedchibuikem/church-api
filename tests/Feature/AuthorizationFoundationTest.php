<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\RolePermission;
use App\Models\ScopeAssignment;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class AuthorizationFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creates_audited_role_permission_and_scope_assignments_with_opaque_identifiers(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();
        $role = Role::factory()->create([
            'code' => 'church.attendance_reviewer',
            'name' => 'Attendance reviewer',
        ]);
        $permission = Permission::factory()->create(['code' => 'church.attendance.view']);

        $rolePermission = $this->app->make(GrantPermissionToRoleAction::class)
            ->handle($role, $permission, $actor);
        $roleAssignment = $this->app->make(AssignRoleToUserAction::class)
            ->handle($user, $role, $actor);
        $scopeAssignment = $this->app->make(AssignScopeToRoleAssignmentAction::class)
            ->handle($roleAssignment, new ScopeReference('church', '01JCHURCH00000000000000001'), $actor);

        $this->assertModelExists($rolePermission);
        $this->assertModelExists($roleAssignment);
        $this->assertModelExists($scopeAssignment);
        $this->assertTrue(Str::isUlid($permission->public_id));
        $this->assertTrue(Str::isUlid($role->public_id));
        $this->assertTrue(Str::isUlid($rolePermission->public_id));
        $this->assertTrue(Str::isUlid($roleAssignment->public_id));
        $this->assertTrue(Str::isUlid($scopeAssignment->public_id));
        $this->assertSame($permission->getKey(), $rolePermission->permission_id);
        $this->assertSame($role->getKey(), $roleAssignment->role_id);
        $this->assertSame($user->getKey(), $roleAssignment->user_id);
        $this->assertSame('church', $scopeAssignment->scope_type);
        $this->assertSame('01JCHURCH00000000000000001', $scopeAssignment->scope_key);
        $this->assertSame(
            [
                'identity.role.permission_granted',
                'identity.role.assigned',
                'organization.scope.assigned',
            ],
            AuditEvent::query()->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_repeating_assignment_actions_is_idempotent(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        $grantPermission = $this->app->make(GrantPermissionToRoleAction::class);
        $assignRole = $this->app->make(AssignRoleToUserAction::class);
        $assignScope = $this->app->make(AssignScopeToRoleAssignmentAction::class);
        $scope = new ScopeReference('country', 'NG');

        $firstGrant = $grantPermission->handle($role, $permission);
        $firstRoleAssignment = $assignRole->handle($user, $role);
        $firstScopeAssignment = $assignScope->handle($firstRoleAssignment, $scope);
        $secondGrant = $grantPermission->handle($role, $permission);
        $secondRoleAssignment = $assignRole->handle($user, $role);
        $secondScopeAssignment = $assignScope->handle($secondRoleAssignment, $scope);

        $this->assertSame($firstGrant->getKey(), $secondGrant->getKey());
        $this->assertSame($firstRoleAssignment->getKey(), $secondRoleAssignment->getKey());
        $this->assertSame($firstScopeAssignment->getKey(), $secondScopeAssignment->getKey());
        $this->assertSame(1, RolePermission::query()->count());
        $this->assertSame(1, RoleAssignment::query()->count());
        $this->assertSame(1, ScopeAssignment::query()->count());
        $this->assertSame(3, AuditEvent::query()->count());
    }

    public function test_rejects_unstable_permission_codes_without_writing_a_record(): void
    {
        $wasRejected = false;

        try {
            Permission::factory()->create(['code' => 'Invalid Permission Code']);
            $this->fail('Expected the unstable permission code to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, Permission::query()->count());
    }

    public function test_rejects_attaching_a_scope_to_an_expired_role_assignment(): void
    {
        $roleAssignment = RoleAssignment::factory()->expired()->create();
        $wasRejected = false;

        try {
            $this->app->make(AssignScopeToRoleAssignmentAction::class)
                ->handle($roleAssignment, new ScopeReference('global', 'platform'));
            $this->fail('Expected the expired role assignment to reject a new scope.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, ScopeAssignment::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_role_revocation_state_cannot_be_mass_assigned(): void
    {
        $roleAssignment = RoleAssignment::factory()->create();

        $roleAssignment->fill(['revoked_at' => now()]);

        $this->assertFalse($roleAssignment->isDirty('revoked_at'));
        $this->assertNull($roleAssignment->revoked_at);
    }
}
