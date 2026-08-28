<?php

namespace Tests\Feature;

use App\Kca\KcaApplicationState;
use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\EventRegistration;
use App\Models\KcaApplication;
use App\Models\Location;
use App\Models\MinistryEvent;
use App\Models\Permission;
use App\Models\Person;
use App\Models\Role;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminDomainOperationsApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_press_operator_can_create_publication(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['press.publications.manage'], $scope);
        $this->authenticate($actor);

        $this->withHeaders([...$this->headers($scope), 'Idempotency-Key' => 'press-create-0001'])
            ->postJson('/api/v1/admin/press/publications', [
                'title' => 'Faithful Foundations',
                'publisher_name' => 'Family House Press',
                'language_code' => 'en-GB',
                'format' => 'pdf',
                'category' => 'discipleship',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Faithful Foundations')
            ->assertJsonPath('data.status', 'manuscript');
    }

    public function test_kca_operator_can_transition_application_when_factory_state_allows(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['kca.applications.transition'], $scope);
        $this->authenticate($actor);
        $application = KcaApplication::factory()->create([
            'status' => KcaApplicationState::Received,
        ]);

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/kca/applications/{$application->public_id}/transitions", [
                'status' => 'reviewed',
                'reason_code' => 'intake_started',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed');
    }

    public function test_platform_admin_can_assign_role_to_user(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['identity.roles.assign'], $scope);
        $this->authenticate($actor);
        $target = User::factory()->create();
        $role = Role::factory()->create();

        $this->withHeaders($this->headers($scope))
            ->postJson("/api/v1/admin/users/{$target->public_id}/role-assignments", [
                'role_id' => $role->public_id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $target->public_id)
            ->assertJsonPath('data.role_id', $role->public_id);
    }

    public function test_church_operator_can_start_membership(): void
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
        $person = Person::factory()->create();
        $scope = new ScopeReference('administrative_unit', $unit->public_id);
        $actor = $this->actorWithPermissions(['church.memberships.manage'], $scope);
        $this->authenticate($actor);

        $this->withHeaders($this->headers($scope))
            ->postJson('/api/v1/admin/church/memberships', [
                'person_id' => $person->public_id,
                'church_id' => $church->public_id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.person_id', $person->public_id)
            ->assertJsonPath('data.church_id', $church->public_id)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_finance_payment_intent_returns_governance_denial(): void
    {
        $scope = new ScopeReference('global', 'platform');
        $actor = $this->actorWithPermissions(['finance.payment_intents.create'], $scope);
        $this->authenticate($actor);
        $event = MinistryEvent::factory()->create([
            'fee_amount_minor' => 2500,
            'fee_currency' => 'NGN',
        ]);
        $registration = EventRegistration::factory()->for($event, 'event')->create();

        $this->withHeaders([...$this->headers($scope), 'Idempotency-Key' => 'payment-intent-0001'])
            ->postJson('/api/v1/admin/finance/payment-intents', [
                'event_registration_id' => $registration->public_id,
            ])
            ->assertStatus(422);
    }

    /** @param array<int, string> $permissionCodes */
    private function actorWithPermissions(array $permissionCodes, ScopeReference $scope): User
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create();

        foreach ($permissionCodes as $permissionCode) {
            $permission = Permission::factory()->create(['code' => $permissionCode]);
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
