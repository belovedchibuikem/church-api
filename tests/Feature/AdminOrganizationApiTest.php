<?php

namespace Tests\Feature;

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\AuditEvent;
use App\Models\Church;
use App\Models\Country;
use App\Models\HomeChurch;
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
use Illuminate\Support\Facades\Schema;
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

    public function test_country_can_be_shown_by_iso_and_updated(): void
    {
        $country = Country::factory()->create(['iso_code' => 'NG', 'name' => 'Nigeria']);
        $actor = $this->actorWithPermissions([
            'organization.countries.view',
            'organization.countries.manage',
        ], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);
        $headers = $this->globalHeaders();

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/organization/countries/NG')
            ->assertOk()
            ->assertJsonPath('data.name', 'Nigeria')
            ->assertJsonPath('data.stats.units', 0);

        $this->withHeaders($headers)
            ->patchJson("/api/v1/admin/organization/countries/{$country->public_id}", ['name' => 'Federal Republic of Nigeria'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Federal Republic of Nigeria');

        $this->assertTrue(AuditEvent::query()->where('action', 'organization.country.updated')->exists());
    }

    public function test_country_profile_fields_are_validated_and_persisted(): void
    {
        if (! Schema::hasColumn('countries', 'calling_code')) {
            $this->markTestSkipped('Run migration 2026_08_31_140000_add_profile_fields_to_countries_table.');
        }
        $actor = $this->actorWithPermissions([
            'organization.countries.view',
            'organization.countries.manage',
        ], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);
        $headers = $this->globalHeaders();

        $countryId = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/organization/countries', [
                'iso_code' => 'KE',
                'name' => 'Kenya',
                'calling_code' => '+254',
                'currency_code' => 'kes',
                'default_timezone' => 'Africa/Nairobi',
                'locale' => 'en-KE',
                'local_name' => 'Kenya',
            ])
            ->assertCreated()
            ->assertJsonPath('data.calling_code', '+254')
            ->assertJsonPath('data.currency_code', 'KES')
            ->assertJsonPath('data.default_timezone', 'Africa/Nairobi')
            ->json('data.id');

        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/organization/countries', [
                'iso_code' => 'TZ',
                'name' => 'Tanzania',
                'calling_code' => 'not-a-code',
            ])
            ->assertUnprocessable();

        $this->withHeaders($headers)
            ->patchJson("/api/v1/admin/organization/countries/{$countryId}", [
                'name' => 'Republic of Kenya',
                'locale' => 'sw-KE',
            ])
            ->assertOk()
            ->assertJsonPath('data.locale', 'sw-KE')
            ->assertJsonPath('data.name', 'Republic of Kenya');
    }

    public function test_units_can_be_filtered_to_root_or_nested_nodes(): void
    {
        $country = Country::factory()->create();
        $level = AdministrativeLevel::factory()->for($country)->create();
        $root = AdministrativeUnit::factory()->for($country)->for($level, 'administrativeLevel')->create(['parent_id' => null]);
        AdministrativeUnit::factory()->for($country)->for($level, 'administrativeLevel')->create(['parent_id' => $root->getKey()]);

        $actor = $this->actorWithPermissions(['organization.units.view'], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);
        $headers = $this->globalHeaders();

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/organization/units?filter[root]=1')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $root->public_id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/organization/units?filter[nested]=1')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.parent.id', $root->public_id);
    }

    public function test_country_delete_is_conflict_not_hard_delete(): void
    {
        $country = Country::factory()->create();
        $actor = $this->actorWithPermissions(['organization.countries.manage'], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);

        $this->withHeaders($this->globalHeaders())
            ->deleteJson("/api/v1/admin/organization/countries/{$country->public_id}")
            ->assertConflict();

        $this->assertTrue(Country::query()->whereKey($country->getKey())->exists());
    }

    public function test_root_units_filter_and_unit_show_are_scoped(): void
    {
        $country = Country::factory()->create();
        $levelOne = AdministrativeLevel::factory()->for($country)->create(['sort_order' => 1]);
        $levelTwo = AdministrativeLevel::factory()->for($country)->create(['sort_order' => 2]);
        $root = AdministrativeUnit::factory()->for($country)->for($levelOne, 'administrativeLevel')->create(['name' => 'Lagos']);
        AdministrativeUnit::factory()->for($country)->for($levelTwo, 'administrativeLevel')->create([
            'parent_id' => $root->getKey(),
            'name' => 'Ikeja',
        ]);
        $actor = $this->actorWithPermissions(['organization.units.view'], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);
        $headers = $this->globalHeaders();

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/organization/units?filter[root]=1&filter[country_id]='.$country->public_id)
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.name', 'Lagos');

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/organization/units/'.$root->public_id)
            ->assertOk()
            ->assertJsonPath('data.stats.children', 1);
    }

    public function test_map_returns_only_coordinated_locations(): void
    {
        $country = Country::factory()->create();
        Location::factory()->for($country)->create(['name' => 'Pinned', 'latitude' => 6.5, 'longitude' => 3.3]);
        Location::factory()->for($country)->create(['name' => 'Unpinned', 'latitude' => null, 'longitude' => null]);
        $actor = $this->actorWithPermissions(['organization.locations.view'], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);

        $this->withHeaders($this->globalHeaders())
            ->getJson('/api/v1/admin/organization/map')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.name', 'Pinned');
    }

    public function test_church_tree_requires_church_view_permission(): void
    {
        $actor = $this->actorWithPermissions(['organization.countries.view'], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);

        $this->withHeaders($this->globalHeaders())
            ->getJson('/api/v1/admin/organization/church-tree')
            ->assertForbidden();
    }

    public function test_church_tree_and_territory_report_use_live_records(): void
    {
        $country = Country::factory()->create();
        $level = AdministrativeLevel::factory()->for($country)->create(['sort_order' => 1]);
        $unit = AdministrativeUnit::factory()->for($country)->for($level, 'administrativeLevel')->create();
        $church = Church::factory()->for($unit, 'administrativeUnit')->create(['name' => 'Covenant Place']);
        HomeChurch::factory()->for($church)->create(['name' => 'Victory Home']);

        $actor = $this->actorWithPermissions([
            'church.churches.view',
            'church.home_churches.view',
            'organization.units.view',
        ], new ScopeReference('global', 'platform'));
        $this->authenticate($actor);
        $headers = $this->globalHeaders();

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/organization/church-tree')
            ->assertOk()
            ->assertJsonPath('data.0.label', $country->name)
            ->assertJsonPath('data.0.children.0.children.0.label', 'Covenant Place');

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/organization/home-church-tree')
            ->assertOk()
            ->assertJsonPath('data.0.children.0.label', 'Victory Home');

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/organization/territory-report')
            ->assertOk()
            ->assertJsonPath('data.0.stats.churches', 1);
    }

    public function test_unauthenticated_organization_mutation_returns_401(): void
    {
        $this->withHeaders($this->globalHeaders())
            ->postJson('/api/v1/admin/organization/countries', ['iso_code' => 'ZZ', 'name' => 'Nowhere'])
            ->assertUnauthorized();
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
