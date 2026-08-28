<?php

namespace Tests\Feature;

use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\AccessDecisionReason;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\AuthorizationDecisionService;
use App\Support\Authorization\Contracts\ScopeContainmentResolver;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use App\Support\Organization\CreateAdministrativeLevelAction;
use App\Support\Organization\CreateAdministrativeUnitAction;
use App\Support\Organization\CreateCountryAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DatabaseScopeContainmentResolverTest extends TestCase
{
    use DatabaseTransactions;

    public function test_preserves_exact_matching_for_unknown_scope_types(): void
    {
        $actor = User::factory()->create();
        $resolver = $this->app->make(ScopeContainmentResolver::class);

        $this->assertTrue($resolver->contains(
            new ScopeReference('future_scope', 'opaque-reference'),
            new ScopeReference('future_scope', 'opaque-reference'),
            $actor,
        ));
        $this->assertFalse($resolver->contains(
            new ScopeReference('future_scope', 'opaque-reference'),
            new ScopeReference('future_scope', 'different-reference'),
            $actor,
        ));
        $this->assertFalse($resolver->contains(
            new ScopeReference('future_scope', 'opaque-reference'),
            new ScopeReference('different_scope', 'opaque-reference'),
            $actor,
        ));
    }

    public function test_global_platform_scope_contains_only_approved_organizational_scope_types(): void
    {
        $actor = User::factory()->create();
        $resolver = $this->app->make(ScopeContainmentResolver::class);
        $global = new ScopeReference('global', 'platform');

        foreach (['country', 'administrative_unit', 'church', 'home_church', 'kca_cohort', 'mission_crusade'] as $scopeType) {
            $this->assertTrue($resolver->contains($global, new ScopeReference($scopeType, 'opaque-reference'), $actor));
        }

        $this->assertFalse($resolver->contains($global, new ScopeReference('own_record', $actor->public_id), $actor));
        $this->assertFalse($resolver->contains($global, new ScopeReference('future_scope', 'opaque-reference'), $actor));
        $this->assertFalse($resolver->contains(new ScopeReference('global', 'regional'), new ScopeReference('church', 'opaque-reference'), $actor));
    }

    public function test_own_record_scope_matches_only_authenticated_user_or_canonical_person(): void
    {
        $actor = User::factory()->withPerson()->create();
        $otherUser = User::factory()->withPerson()->create();
        $resolver = $this->app->make(ScopeContainmentResolver::class);

        $this->assertTrue($resolver->contains(
            new ScopeReference('own_record', $actor->public_id),
            new ScopeReference('own_record', $actor->public_id),
            $actor,
        ));
        $this->assertTrue($resolver->contains(
            new ScopeReference('own_record', $actor->person->public_id),
            new ScopeReference('own_record', $actor->person->public_id),
            $actor,
        ));
        $this->assertFalse($resolver->contains(
            new ScopeReference('own_record', $otherUser->public_id),
            new ScopeReference('own_record', $otherUser->public_id),
            $actor,
        ));
    }

    public function test_country_scope_contains_only_units_inside_that_country(): void
    {
        $actor = User::factory()->create();
        $resolver = $this->app->make(ScopeContainmentResolver::class);
        [$firstCountry, $firstRegion] = $this->createCountryAndRegion('GH', 'Ghana');
        [$secondCountry, $secondRegion] = $this->createCountryAndRegion('UG', 'Uganda');

        $this->assertTrue($resolver->contains(
            new ScopeReference('country', $firstCountry->public_id),
            new ScopeReference('administrative_unit', $firstRegion->public_id),
            $actor,
        ));
        $this->assertFalse($resolver->contains(
            new ScopeReference('country', $firstCountry->public_id),
            new ScopeReference('administrative_unit', $secondRegion->public_id),
            $actor,
        ));
        $this->assertTrue($resolver->contains(
            new ScopeReference('country', $secondCountry->public_id),
            new ScopeReference('administrative_unit', $secondRegion->public_id),
            $actor,
        ));
    }

    public function test_administrative_unit_scope_contains_descendants_but_not_other_units(): void
    {
        $actor = User::factory()->create();
        $resolver = $this->app->make(ScopeContainmentResolver::class);
        [$country, $region, $district, $ward, $siblingDistrict] = $this->createHierarchy();
        [, $foreignRegion] = $this->createCountryAndRegion('UG', 'Uganda');

        $this->assertTrue($resolver->contains(
            new ScopeReference('administrative_unit', $region->public_id),
            new ScopeReference('administrative_unit', $ward->public_id),
            $actor,
        ));
        $this->assertTrue($resolver->contains(
            new ScopeReference('administrative_unit', $district->public_id),
            new ScopeReference('administrative_unit', $ward->public_id),
            $actor,
        ));
        $this->assertFalse($resolver->contains(
            new ScopeReference('administrative_unit', $district->public_id),
            new ScopeReference('administrative_unit', $region->public_id),
            $actor,
        ));
        $this->assertFalse($resolver->contains(
            new ScopeReference('administrative_unit', $district->public_id),
            new ScopeReference('administrative_unit', $siblingDistrict->public_id),
            $actor,
        ));
        $this->assertFalse($resolver->contains(
            new ScopeReference('administrative_unit', $district->public_id),
            new ScopeReference('administrative_unit', $foreignRegion->public_id),
            $actor,
        ));
        $this->assertSame('GH', $country->iso_code);
    }

    public function test_authorization_service_honors_a_country_assignment_for_its_units(): void
    {
        [$country, , , $ward] = $this->createHierarchy();
        $actor = User::factory()->create();
        $role = Role::factory()->create(['code' => 'geography_reader']);
        $permission = Permission::factory()->create(['code' => 'organization.locations.view']);
        $this->app->make(GrantPermissionToRoleAction::class)->handle($role, $permission);
        $roleAssignment = $this->app->make(AssignRoleToUserAction::class)->handle($actor, $role);
        $this->app->make(AssignScopeToRoleAssignmentAction::class)->handle(
            $roleAssignment,
            new ScopeReference('country', $country->public_id),
        );

        $result = $this->app->make(AuthorizationDecisionService::class)->decide(
            $actor,
            $permission->code,
            new ScopeReference('administrative_unit', $ward->public_id),
        );

        $this->assertTrue($result->allowed);
        $this->assertSame(AccessDecisionReason::Allowed, $result->reason);
        $this->assertSame($roleAssignment->getKey(), $result->record->matched_role_assignment_id);
        $this->assertSame('administrative_unit', $result->record->scope_type);
        $this->assertSame($ward->public_id, $result->record->scope_key);
    }

    /**
     * @return array{Country, AdministrativeUnit}
     */
    private function createCountryAndRegion(string $isoCode, string $countryName): array
    {
        $country = $this->app->make(CreateCountryAction::class)->handle($isoCode, $countryName);
        $level = $this->app->make(CreateAdministrativeLevelAction::class)
            ->handle($country, 'region', 'Region', 10);
        $region = $this->app->make(CreateAdministrativeUnitAction::class)
            ->handle($country, $level, $countryName.' Region');

        return [$country, $region];
    }

    /**
     * @return array{Country, AdministrativeUnit, AdministrativeUnit, AdministrativeUnit, AdministrativeUnit}
     */
    private function createHierarchy(): array
    {
        $country = $this->app->make(CreateCountryAction::class)->handle('GH', 'Ghana');
        $createLevel = $this->app->make(CreateAdministrativeLevelAction::class);
        $regionLevel = $createLevel->handle($country, 'region', 'Region', 10);
        $districtLevel = $createLevel->handle($country, 'district', 'District', 20);
        $wardLevel = $createLevel->handle($country, 'ward', 'Ward', 30);
        $createUnit = $this->app->make(CreateAdministrativeUnitAction::class);
        $region = $createUnit->handle($country, $regionLevel, 'Coastal Region');
        $district = $createUnit->handle($country, $districtLevel, 'Harbour District', $region);
        $ward = $createUnit->handle($country, $wardLevel, 'Ocean Ward', $district);
        $siblingDistrict = $createUnit->handle($country, $districtLevel, 'Forest District', $region);

        return [$country, $region, $district, $ward, $siblingDistrict];
    }
}
