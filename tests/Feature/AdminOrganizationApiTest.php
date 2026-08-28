<?php

namespace Tests\Feature;

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\AuditEvent;
use App\Models\Country;
use App\Models\Location;
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

class AdminOrganizationApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_requires_authentication(): void
    {
        $this->withHeaders($this->globalHeaders())
            ->getJson('/api/v1/admin/organization/countries')
            ->assertUnauthorized();
    }

    public function test_global_administrator_can_build_audited_geography_hierarchy(): void
    {
        $actor = $this->actorWithPermissions([
            'organization.countries.view',
            'organization.countries.manage',
            'organization.units.view',
            'organization.units.manage',
            'organization.locations.view',
            'organization.locations.manage',
        ], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);
        $headers = $this->globalHeaders();

        $countryId = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/organization/countries', ['iso_code' => 'GH', 'name' => 'Ghana'])
            ->assertCreated()
            ->assertJsonPath('data.iso_code', 'GH')
            ->json('data.id');

        $regionLevelId = $this->withHeaders($headers)
            ->postJson("/api/v1/admin/organization/countries/{$countryId}/levels", [
                'code' => 'region', 'name' => 'Region', 'sort_order' => 1,
            ])->assertCreated()->json('data.id');
        $districtLevelId = $this->withHeaders($headers)
            ->postJson("/api/v1/admin/organization/countries/{$countryId}/levels", [
                'code' => 'district', 'name' => 'District', 'sort_order' => 2,
            ])->assertCreated()->json('data.id');

        $regionId = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/organization/units', [
                'country_id' => $countryId,
                'administrative_level_id' => $regionLevelId,
                'name' => 'Greater Accra',
                'reference_code' => 'GH-AA',
            ])->assertCreated()->json('data.id');
        $districtId = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/organization/units', [
                'country_id' => $countryId,
                'administrative_level_id' => $districtLevelId,
                'parent_id' => $regionId,
                'name' => 'Accra Metropolitan',
                'reference_code' => 'GH-AA-AMA',
            ])->assertCreated()->assertJsonPath('data.parent.id', $regionId)->json('data.id');

        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/organization/locations', [
                'country_id' => $countryId,
                'administrative_unit_id' => $districtId,
                'name' => 'Accra Campus',
                'timezone' => 'Africa/Accra',
                'latitude' => 5.6037,
                'longitude' => -0.187,
            ])
            ->assertCreated()
            ->assertJsonPath('data.administrative_unit.id', $districtId)
            ->assertJsonPath('data.coordinates.latitude', 5.6037);

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/organization/units?sort=name')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 2);

        $this->assertTrue(AuditEvent::query()->where('action', 'organization.country.created')->exists());
        $this->assertSame(2, AuditEvent::query()->where('action', 'organization.administrative_level.created')->count());
        $this->assertSame(2, AuditEvent::query()->where('action', 'organization.administrative_unit.created')->count());
        $this->assertTrue(AuditEvent::query()->where('action', 'organization.location.created')->exists());
    }

    public function test_administrative_unit_scope_lists_only_its_subtree_and_locations(): void
    {
        $country = Country::factory()->create();
        $levelOne = AdministrativeLevel::factory()->for($country)->create(['sort_order' => 1]);
        $levelTwo = AdministrativeLevel::factory()->for($country)->create(['sort_order' => 2]);
        $root = AdministrativeUnit::factory()->for($country)->for($levelOne, 'administrativeLevel')->create();
        $child = AdministrativeUnit::factory()->for($country)->for($levelTwo, 'administrativeLevel')->create(['parent_id' => $root->getKey()]);
        $sibling = AdministrativeUnit::factory()->for($country)->for($levelOne, 'administrativeLevel')->create();
        Location::factory()->for($country)->create(['administrative_unit_id' => $child->getKey(), 'name' => 'Visible']);
        Location::factory()->for($country)->create(['administrative_unit_id' => $sibling->getKey(), 'name' => 'Hidden']);
        $scope = new ScopeReference('administrative_unit', $root->public_id);
        $actor = $this->actorWithPermissions(['organization.units.view', 'organization.locations.view'], $scope);
        $this->authenticate($actor);
        $headers = ['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key];

        $unitIds = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/organization/units')
            ->assertOk()
            ->json('data.*.id');
        $this->assertEqualsCanonicalizing([$root->public_id, $child->public_id], $unitIds);

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/organization/locations')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.name', 'Visible')
            ->assertJsonMissing(['name' => 'Hidden']);
    }

    public function test_country_level_mutation_outside_requested_scope_is_not_found(): void
    {
        $country = Country::factory()->create();
        $level = AdministrativeLevel::factory()->for($country)->create(['sort_order' => 1]);
        $unit = AdministrativeUnit::factory()->for($country)->for($level, 'administrativeLevel')->create();
        $scope = new ScopeReference('administrative_unit', $unit->public_id);
        $actor = $this->actorWithPermissions(['organization.countries.manage'], $scope);
        $this->authenticate($actor);

        $this->withHeaders(['X-Scope-Type' => $scope->type, 'X-Scope-ID' => $scope->key])
            ->postJson("/api/v1/admin/organization/countries/{$country->public_id}/levels", [
                'code' => 'district', 'name' => 'District', 'sort_order' => 2,
            ])->assertNotFound();
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
    private function globalHeaders(): array
    {
        return ['X-Scope-Type' => 'global', 'X-Scope-ID' => 'platform'];
    }
}
