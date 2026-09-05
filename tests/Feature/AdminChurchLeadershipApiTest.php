<?php

namespace Tests\Feature;

use App\Models\AdministrativeUnit;
use App\Models\AuditEvent;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ChurchRoleAssignment;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Person;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\SecuritySession;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\AuthorizationBundleCatalog;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ProvisionAuthorizationBundlesAction;
use App\Support\Authorization\ScopeReference;
use App\Support\Church\ChurchLeadershipCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminChurchLeadershipApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_church_operator_can_appoint_leader_with_membership_and_optional_admin_access(): void
    {
        Notification::fake();
        $this->app->make(ProvisionAuthorizationBundlesAction::class)->handle();

        [$church, $headers] = $this->churchContext();
        $person = Person::factory()->withProfile()->create();

        $this->withHeaders($headers)->postJson('/api/v1/admin/church/role-assignments', [
            'church_id' => $church->public_id,
            'person_id' => $person->public_id,
            'role_type' => 'leader',
            'title' => 'Senior Pastor',
            'grant_admin_access' => true,
            'admin_email' => 'pastor@example.test',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Senior Pastor')
            ->assertJsonPath('data.admin_access_granted', true);

        $this->assertTrue(ChurchMembership::query()
            ->where('church_id', $church->getKey())
            ->where('person_id', $person->getKey())
            ->where('active_marker', 1)
            ->exists());

        $user = User::query()->where('email', 'pastor@example.test')->firstOrFail();
        $this->assertSame($person->getKey(), $user->person_id);
        $this->assertTrue(
            RoleAssignment::query()
                ->where('user_id', $user->getKey())
                ->active()
                ->whereHas('role', fn ($query) => $query->where(
                    'code',
                    AuthorizationBundleCatalog::CHURCH_OPERATIONS_ADMINISTRATOR_ROLE,
                ))
                ->whereHas('scopeAssignments', fn ($query) => $query
                    ->where('scope_type', 'church')
                    ->where('scope_key', $church->public_id))
                ->exists(),
        );
        Notification::assertSentTo($user, QueuedResetPassword::class);
        $this->assertTrue(AuditEvent::query()->where('action', 'church.leadership.admin_access_granted')->exists());
    }

    public function test_appointing_an_existing_member_as_associate_pastor_succeeds(): void
    {
        [$church, $headers] = $this->churchContext();
        $person = Person::factory()->withProfile()->create();
        ChurchMembership::factory()->create([
            'church_id' => $church->getKey(),
            'person_id' => $person->getKey(),
        ]);

        $this->withHeaders($headers)->postJson('/api/v1/admin/church/role-assignments', [
            'church_id' => $church->public_id,
            'person_id' => $person->public_id,
            'role_type' => 'leader',
            'title' => 'Associate Pastor',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Associate Pastor');
    }

    public function test_appointing_a_member_of_another_church_as_associate_pastor_succeeds(): void
    {
        [$church, $headers] = $this->churchContext();
        $otherChurch = Church::factory()->create();
        $person = Person::factory()->withProfile()->create();
        ChurchMembership::factory()->create([
            'church_id' => $otherChurch->getKey(),
            'person_id' => $person->getKey(),
        ]);

        $this->withHeaders($headers)->postJson('/api/v1/admin/church/role-assignments', [
            'church_id' => $church->public_id,
            'person_id' => $person->public_id,
            'role_type' => 'leader',
            'title' => 'Associate Pastor',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Associate Pastor');
    }

    public function test_resident_pastor_and_deaconess_titles_are_accepted(): void
    {
        [$church, $headers] = $this->churchContext();
        $pastor = Person::factory()->withProfile()->create();
        $deaconess = Person::factory()->withProfile()->create();

        $this->withHeaders($headers)->postJson('/api/v1/admin/church/role-assignments', [
            'church_id' => $church->public_id,
            'person_id' => $pastor->public_id,
            'role_type' => 'leader',
            'title' => 'Resident Pastor',
        ])->assertCreated()->assertJsonPath('data.title', 'Resident Pastor');

        $this->withHeaders($headers)->postJson('/api/v1/admin/church/role-assignments', [
            'church_id' => $church->public_id,
            'person_id' => $deaconess->public_id,
            'role_type' => 'leader',
            'title' => 'Deaconess',
        ])->assertCreated()->assertJsonPath('data.title', 'Deaconess');
    }

    public function test_leader_appointment_rejects_invalid_title_and_leader_cap(): void
    {
        [$church, $headers] = $this->churchContext();
        $person = Person::factory()->withProfile()->create();

        $this->withHeaders($headers)->postJson('/api/v1/admin/church/role-assignments', [
            'church_id' => $church->public_id,
            'person_id' => $person->public_id,
            'role_type' => 'leader',
            'title' => 'Bishop of Everything',
        ])->assertStatus(422);

        for ($index = 0; $index < ChurchLeadershipCatalog::MAX_ACTIVE_LEADERS_PER_CHURCH; $index++) {
            ChurchRoleAssignment::query()->create([
                'church_id' => $church->getKey(),
                'person_id' => Person::factory()->withProfile()->create()->getKey(),
                'role_type' => 'leader',
                'title' => 'Elder',
                'status' => 'active',
                'started_at' => now()->utc(),
            ]);
        }

        $this->withHeaders($headers)->postJson('/api/v1/admin/church/role-assignments', [
            'church_id' => $church->public_id,
            'person_id' => $person->public_id,
            'role_type' => 'leader',
            'title' => 'Elder',
        ])->assertStatus(422);
    }

    public function test_ending_leader_assignment_revokes_church_scoped_admin_access(): void
    {
        $this->app->make(ProvisionAuthorizationBundlesAction::class)->handle();
        [$church, $headers] = $this->churchContext();
        $person = Person::factory()->withProfile()->create();

        $assignmentId = $this->withHeaders($headers)->postJson('/api/v1/admin/church/role-assignments', [
            'church_id' => $church->public_id,
            'person_id' => $person->public_id,
            'role_type' => 'leader',
            'title' => 'Elder',
            'grant_admin_access' => true,
            'admin_email' => 'elder@example.test',
        ])->assertCreated()->json('data.id');

        $user = User::query()->where('email', 'elder@example.test')->firstOrFail();

        $this->withHeaders($headers)->putJson("/api/v1/admin/church/role-assignments/{$assignmentId}", [
            'church_id' => $church->public_id,
            'person_id' => $person->public_id,
            'role_type' => 'leader',
            'title' => 'Elder',
            'status' => 'ended',
            'ended_at' => now()->utc()->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.status', 'ended');

        $this->assertFalse(
            RoleAssignment::query()
                ->where('user_id', $user->getKey())
                ->active()
                ->whereHas('role', fn ($query) => $query->where(
                    'code',
                    AuthorizationBundleCatalog::CHURCH_OPERATIONS_ADMINISTRATOR_ROLE,
                ))
                ->exists(),
        );
        $this->assertTrue(AuditEvent::query()->where('action', 'church.leadership.admin_access_revoked')->exists());
    }

    public function test_capabilities_snapshot_includes_roles_and_scopes(): void
    {
        $this->app->make(ProvisionAuthorizationBundlesAction::class)->handle();
        $church = Church::factory()->create();
        $actor = User::factory()->create();
        $role = Role::query()->where('code', AuthorizationBundleCatalog::CHURCH_OPERATIONS_ADMINISTRATOR_ROLE)->firstOrFail();
        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle(
            $assignment,
            new ScopeReference('church', $church->public_id),
        );
        $this->authenticate($actor);

        $this->getJson('/api/v1/user/capabilities')
            ->assertOk()
            ->assertJsonPath('data.roles.0.code', AuthorizationBundleCatalog::CHURCH_OPERATIONS_ADMINISTRATOR_ROLE)
            ->assertJsonPath('data.roles.0.scopes.0.type', 'church')
            ->assertJsonPath('data.roles.0.scopes.0.key', $church->public_id);
    }

    /** @return array{0: Church, 1: array<string, string>} */
    private function churchContext(): array
    {
        $unit = AdministrativeUnit::factory()->create();
        $location = Location::factory()->create([
            'country_id' => $unit->country_id,
            'administrative_unit_id' => $unit->getKey(),
        ]);
        $church = Church::factory()->create([
            'location_id' => $location->getKey(),
            'administrative_unit_id' => $unit->getKey(),
        ]);
        $scope = new ScopeReference('administrative_unit', $unit->public_id);
        $actor = $this->actorWithPermissions(['church.churches.manage'], $scope);
        $this->authenticate($actor);

        return [$church, $this->headers($scope)];
    }

    /** @param array<int, string> $permissionCodes */
    private function actorWithPermissions(array $permissionCodes, ScopeReference $scope): User
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();

        foreach ($permissionCodes as $permissionCode) {
            $permission = Permission::query()->firstOrCreate(['code' => $permissionCode]);
            $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        }

        $assignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle($assignment, $scope);

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
    private function headers(ScopeReference $scope): array
    {
        return ['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key];
    }
}
