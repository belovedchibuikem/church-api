<?php

namespace Tests\Feature;

use App\Exceptions\AccessDecisionImmutableException;
use App\Models\AccessDecision;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\ScopeAssignment;
use App\Models\User;
use App\Support\Authorization\AccessDecisionReason;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\AuthorizationBundleCatalog;
use App\Support\Authorization\AuthorizationDecisionService;
use App\Support\Authorization\Contracts\ScopeContainmentResolver;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class AuthorizationDecisionServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_allows_an_assigned_permission_inside_an_exact_scope_and_records_the_decision(): void
    {
        $correlationId = 'db7670e7-caa4-4c6f-b6dc-b3c2de684301';
        Context::add('correlation_id', $correlationId);
        [$actor, $roleAssignment] = $this->provisionPermissionAtScope(
            'church.people.view',
            new ScopeReference('church', '01JCHURCH00000000000000001'),
        );

        $result = $this->app->make(AuthorizationDecisionService::class)->decide(
            $actor,
            'church.people.view',
            new ScopeReference('church', '01JCHURCH00000000000000001'),
        );

        $this->assertTrue($result->allowed);
        $this->assertSame(AccessDecisionReason::Allowed, $result->reason);
        $this->assertModelExists($result->record);
        $this->assertTrue(Str::isUlid($result->record->public_id));
        $this->assertSame($actor->getKey(), $result->record->actor_user_id);
        $this->assertSame($roleAssignment->getKey(), $result->record->matched_role_assignment_id);
        $this->assertSame('church.people.view', $result->record->permission_code);
        $this->assertSame('church', $result->record->scope_type);
        $this->assertSame('01JCHURCH00000000000000001', $result->record->scope_key);
        $this->assertSame($correlationId, $result->record->correlation_id);
    }

    public function test_super_administrator_is_allowed_without_an_explicit_permission_grant(): void
    {
        $actor = User::factory()->create();
        $superRole = Role::factory()->create([
            'code' => AuthorizationBundleCatalog::SUPER_ADMINISTRATOR_ROLE,
        ]);
        $roleAssignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $superRole);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle(
            $roleAssignment,
            new ScopeReference('global', 'platform'),
        );

        $result = $this->app->make(AuthorizationDecisionService::class)->decide(
            $actor,
            'kca.orientation.view',
            new ScopeReference('global', 'platform'),
        );

        $this->assertTrue($result->allowed);
        $this->assertSame(AccessDecisionReason::Allowed, $result->reason);
        $this->assertSame($roleAssignment->getKey(), $result->record->matched_role_assignment_id);
    }

    public function test_denies_and_records_a_missing_permission(): void
    {
        $actor = User::factory()->create();

        $result = $this->app->make(AuthorizationDecisionService::class)->decide(
            $actor,
            'finance.refunds.approve',
            new ScopeReference('country', 'NG'),
        );

        $this->assertFalse($result->allowed);
        $this->assertSame(AccessDecisionReason::PermissionNotAssigned, $result->reason);
        $this->assertNull($result->record->matched_role_assignment_id);
        $this->assertSame('finance.refunds.approve', $result->record->permission_code);
        $this->assertSame(AccessDecisionReason::PermissionNotAssigned, $result->record->reason_code);
        $this->assertSame(1, AccessDecision::query()->count());
    }

    public function test_denies_and_records_a_suspended_actor_before_evaluating_assignments(): void
    {
        $actor = User::factory()->suspended()->create();

        $result = $this->app->make(AuthorizationDecisionService::class)->decide(
            $actor,
            'reports.view',
            new ScopeReference('country', 'NG'),
        );

        $this->assertFalse($result->allowed);
        $this->assertSame(AccessDecisionReason::AccountSuspended, $result->reason);
        $this->assertSame(AccessDecisionReason::AccountSuspended, $result->record->reason_code);
        $this->assertNull($result->record->matched_role_assignment_id);
    }

    public function test_denies_and_records_a_permission_without_a_scope_assignment(): void
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create(['code' => 'reports.view']);
        $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);

        $result = $this->app->make(AuthorizationDecisionService::class)->decide(
            $actor,
            'reports.view',
            new ScopeReference('country', 'NG'),
        );

        $this->assertFalse($result->allowed);
        $this->assertSame(AccessDecisionReason::ScopeNotAssigned, $result->reason);
        $this->assertSame(AccessDecisionReason::ScopeNotAssigned, $result->record->reason_code);
    }

    public function test_denies_and_records_a_scope_outside_the_default_exact_match(): void
    {
        [$actor] = $this->provisionPermissionAtScope(
            'church.people.view',
            new ScopeReference('country', 'NG'),
        );

        $result = $this->app->make(AuthorizationDecisionService::class)->decide(
            $actor,
            'church.people.view',
            new ScopeReference('church', '01JCHURCH00000000000000001'),
        );

        $this->assertFalse($result->allowed);
        $this->assertSame(AccessDecisionReason::ScopeNotContained, $result->reason);
        $this->assertSame(AccessDecisionReason::ScopeNotContained, $result->record->reason_code);
    }

    public function test_delegates_hierarchical_scope_semantics_to_the_injected_resolver(): void
    {
        [$actor, $roleAssignment] = $this->provisionPermissionAtScope(
            'church.people.view',
            new ScopeReference('country', 'NG'),
        );
        $resolver = $this->mock(ScopeContainmentResolver::class);
        $resolver->shouldReceive('contains')
            ->once()
            ->withArgs(function (
                ScopeReference $assigned,
                ScopeReference $requested,
                User $resolvedActor,
            ) use ($actor): bool {
                return $assigned->type === 'country'
                    && $assigned->key === 'NG'
                    && $requested->type === 'church'
                    && $requested->key === '01JCHURCH00000000000000001'
                    && $resolvedActor->is($actor);
            })
            ->andReturnTrue();

        $result = $this->app->make(AuthorizationDecisionService::class)->decide(
            $actor,
            'church.people.view',
            new ScopeReference('church', '01JCHURCH00000000000000001'),
        );

        $this->assertTrue($result->allowed);
        $this->assertSame(AccessDecisionReason::Allowed, $result->reason);
        $this->assertSame($roleAssignment->getKey(), $result->record->matched_role_assignment_id);
    }

    public function test_ignores_expired_role_assignments(): void
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create(['code' => 'reports.view']);
        $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        $expiredAssignment = RoleAssignment::factory()
            ->expired()
            ->for($actor)
            ->for($role)
            ->create();
        ScopeAssignment::factory()->for($expiredAssignment)->create([
            'scope_type' => 'country',
            'scope_key' => 'NG',
        ]);

        $result = $this->app->make(AuthorizationDecisionService::class)->decide(
            $actor,
            'reports.view',
            new ScopeReference('country', 'NG'),
        );

        $this->assertFalse($result->allowed);
        $this->assertSame(AccessDecisionReason::PermissionNotAssigned, $result->reason);
    }

    public function test_rejects_invalid_permission_codes_without_recording_a_decision(): void
    {
        $actor = User::factory()->create();
        $wasRejected = false;

        try {
            $this->app->make(AuthorizationDecisionService::class)->decide(
                $actor,
                'Invalid Permission',
                new ScopeReference('global', 'platform'),
            );
            $this->fail('Expected the unstable permission code to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, AccessDecision::query()->count());
    }

    public function test_access_decisions_are_append_only_and_reject_narrative_mass_assignment(): void
    {
        $decision = AccessDecision::factory()->create();
        $wasRejected = false;

        $decision->fill(['narrative' => 'restricted pastoral narrative']);

        try {
            $decision->update(['reason_code' => AccessDecisionReason::ScopeNotContained]);
            $this->fail('Expected the access decision update to be rejected.');
        } catch (AccessDecisionImmutableException) {
            $wasRejected = true;
        }

        $this->assertFalse($decision->isDirty('narrative'));
        $this->assertFalse(array_key_exists('narrative', $decision->getAttributes()));
        $this->assertTrue($wasRejected);
        $this->assertSame(
            AccessDecisionReason::PermissionNotAssigned,
            $decision->fresh()->reason_code,
        );
    }

    public function test_access_decisions_reject_deletion(): void
    {
        $decision = AccessDecision::factory()->create();
        $wasRejected = false;

        try {
            $decision->delete();
            $this->fail('Expected the access decision deletion to be rejected.');
        } catch (AccessDecisionImmutableException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertModelExists($decision);
    }

    /**
     * @return array{User, RoleAssignment}
     */
    private function provisionPermissionAtScope(
        string $permissionCode,
        ScopeReference $scope,
    ): array {
        $actor = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create(['code' => $permissionCode]);
        $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        $roleAssignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle($roleAssignment, $scope);

        return [$actor, $roleAssignment];
    }
}
