<?php

namespace Tests\Feature;

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\AuditEvent;
use App\Models\Country;
use App\Models\Location;
use App\Models\User;
use App\Support\Organization\CreateAdministrativeLevelAction;
use App\Support\Organization\CreateAdministrativeUnitAction;
use App\Support\Organization\CreateCountryAction;
use App\Support\Organization\CreateLocationAction;
use App\Support\Organization\LocationData;
use App\Support\Organization\MoveAdministrativeUnitAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class GeographyOrganizationFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creates_a_generic_audited_hierarchy_and_reusable_location(): void
    {
        $actor = User::factory()->create();
        $country = $this->app->make(CreateCountryAction::class)->handle('ke', 'Kenya', $actor);
        $createLevel = $this->app->make(CreateAdministrativeLevelAction::class);
        $regionLevel = $createLevel->handle($country, 'region', 'Region', 10, $actor);
        $districtLevel = $createLevel->handle($country, 'district', 'District', 20, $actor);
        $createUnit = $this->app->make(CreateAdministrativeUnitAction::class);
        $region = $createUnit->handle($country, $regionLevel, 'Central Region', referenceCode: 'ke-central', actor: $actor);
        $district = $createUnit->handle(
            $country,
            $districtLevel,
            'Highlands District',
            $region,
            'ke-central-highlands',
            $actor,
        );

        $location = $this->app->make(CreateLocationAction::class)->handle(
            new LocationData(
                country: $country,
                name: 'Training Centre',
                timezone: 'Africa/Nairobi',
                administrativeUnit: $district,
                latitude: -1.286389,
                longitude: 36.817223,
                addressLineOne: '100 Mission Road',
                locality: 'Nairobi',
                postalCode: '00100',
            ),
            $actor,
        );

        $this->assertSame('KE', $country->iso_code);
        $this->assertTrue(Str::isUlid($country->public_id));
        $this->assertTrue(Str::isUlid($regionLevel->public_id));
        $this->assertTrue(Str::isUlid($region->public_id));
        $this->assertTrue(Str::isUlid($location->public_id));
        $this->assertSame($region->getKey(), $district->parent_id);
        $this->assertSame($country->getKey(), $location->country_id);
        $this->assertSame($district->getKey(), $location->administrative_unit_id);
        $this->assertSame('Africa/Nairobi', $location->timezone);
        $this->assertSame('-1.2863890', $location->latitude);
        $this->assertSame('36.8172230', $location->longitude);
        $this->assertSame(
            [
                'organization.country.created',
                'organization.administrative_level.created',
                'organization.administrative_level.created',
                'organization.administrative_unit.created',
                'organization.administrative_unit.created',
                'organization.location.created',
            ],
            AuditEvent::query()->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_allows_each_country_to_define_its_own_ordered_levels(): void
    {
        $createCountry = $this->app->make(CreateCountryAction::class);
        $createLevel = $this->app->make(CreateAdministrativeLevelAction::class);
        $firstCountry = $createCountry->handle('GH', 'Ghana');
        $secondCountry = $createCountry->handle('UG', 'Uganda');

        $firstLevel = $createLevel->handle($firstCountry, 'region', 'Region', 10);
        $secondLevel = $createLevel->handle($secondCountry, 'region', 'Region', 10);

        $this->assertSame($firstCountry->getKey(), $firstLevel->country_id);
        $this->assertSame($secondCountry->getKey(), $secondLevel->country_id);
        $this->assertSame(2, AdministrativeLevel::query()->where('code', 'region')->count());
    }

    public function test_rejects_unassigned_iso_country_codes_without_writing_records(): void
    {
        $wasRejected = false;

        try {
            $this->app->make(CreateCountryAction::class)->handle('ZZ', 'Reserved Example');
            $this->fail('Expected the unassigned ISO code to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, Country::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_rejects_cross_country_parentage_without_writing_a_unit(): void
    {
        $createCountry = $this->app->make(CreateCountryAction::class);
        $createLevel = $this->app->make(CreateAdministrativeLevelAction::class);
        $createUnit = $this->app->make(CreateAdministrativeUnitAction::class);
        $firstCountry = $createCountry->handle('GH', 'Ghana');
        $secondCountry = $createCountry->handle('UG', 'Uganda');
        $firstRegionLevel = $createLevel->handle($firstCountry, 'region', 'Region', 10);
        $firstDistrictLevel = $createLevel->handle($firstCountry, 'district', 'District', 20);
        $secondRegionLevel = $createLevel->handle($secondCountry, 'region', 'Region', 10);
        $foreignRegion = $createUnit->handle($secondCountry, $secondRegionLevel, 'Eastern Region');
        $unitCount = AdministrativeUnit::query()->count();
        $auditCount = AuditEvent::query()->count();
        $wasRejected = false;

        try {
            $createUnit->handle(
                $firstCountry,
                $firstDistrictLevel,
                'Invalid District',
                $foreignRegion,
            );
            $this->fail('Expected cross-country parentage to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame($unitCount, AdministrativeUnit::query()->count());
        $this->assertSame($auditCount, AuditEvent::query()->count());
        $this->assertSame($firstCountry->getKey(), $firstRegionLevel->country_id);
    }

    public function test_rejects_a_parent_that_skips_the_immediately_preceding_level(): void
    {
        [$country, $regionLevel, $districtLevel, $wardLevel] = $this->createLevels();
        $createUnit = $this->app->make(CreateAdministrativeUnitAction::class);
        $region = $createUnit->handle($country, $regionLevel, 'Northern Region');
        $unitCount = AdministrativeUnit::query()->count();
        $wasRejected = false;

        try {
            $createUnit->handle($country, $wardLevel, 'Invalid Ward', $region);
            $this->fail('Expected the skipped hierarchy level to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame($unitCount, AdministrativeUnit::query()->count());
        $this->assertSame($country->getKey(), $districtLevel->country_id);
    }

    public function test_rejects_a_move_that_would_create_a_cycle(): void
    {
        [$country, $regionLevel, $districtLevel, $wardLevel] = $this->createLevels();
        $createUnit = $this->app->make(CreateAdministrativeUnitAction::class);
        $region = $createUnit->handle($country, $regionLevel, 'Northern Region');
        $district = $createUnit->handle($country, $districtLevel, 'River District', $region);
        $ward = $createUnit->handle($country, $wardLevel, 'Valley Ward', $district);
        $auditCount = AuditEvent::query()->count();
        $wasRejected = false;

        try {
            $this->app->make(MoveAdministrativeUnitAction::class)->handle($region, $ward);
            $this->fail('Expected cyclic parentage to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertNull($region->fresh()->parent_id);
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    public function test_moves_a_unit_between_valid_parents_and_audits_the_change(): void
    {
        [$country, $regionLevel, $districtLevel] = $this->createLevels();
        $createUnit = $this->app->make(CreateAdministrativeUnitAction::class);
        $firstRegion = $createUnit->handle($country, $regionLevel, 'Northern Region');
        $secondRegion = $createUnit->handle($country, $regionLevel, 'Southern Region');
        $district = $createUnit->handle($country, $districtLevel, 'River District', $firstRegion);

        $movedDistrict = $this->app->make(MoveAdministrativeUnitAction::class)
            ->handle($district, $secondRegion);

        $this->assertSame($secondRegion->getKey(), $movedDistrict->parent_id);
        $this->assertSame(
            'organization.administrative_unit.parent_changed',
            AuditEvent::query()->latest('id')->value('action'),
        );
    }

    public function test_rejects_invalid_timezones_and_coordinates_without_writing_locations(): void
    {
        $country = Country::factory()->create(['iso_code' => 'GH']);
        $invalidTimezoneRejected = false;
        $invalidCoordinatesRejected = false;
        $partialCoordinatesRejected = false;

        try {
            new LocationData($country, 'Invalid timezone', 'Africa/Not_A_Zone');
            $this->fail('Expected the invalid timezone to be rejected.');
        } catch (InvalidArgumentException) {
            $invalidTimezoneRejected = true;
        }

        try {
            new LocationData($country, 'Invalid coordinates', 'Africa/Accra', latitude: 91, longitude: 0);
            $this->fail('Expected the invalid coordinates to be rejected.');
        } catch (InvalidArgumentException) {
            $invalidCoordinatesRejected = true;
        }

        try {
            new LocationData($country, 'Partial coordinates', 'Africa/Accra', latitude: 5.603717);
            $this->fail('Expected the partial coordinates to be rejected.');
        } catch (InvalidArgumentException) {
            $partialCoordinatesRejected = true;
        }

        $this->assertTrue($invalidTimezoneRejected);
        $this->assertTrue($invalidCoordinatesRejected);
        $this->assertTrue($partialCoordinatesRejected);
        $this->assertSame(0, Location::query()->count());
    }

    public function test_rejects_a_location_assigned_to_a_unit_in_another_country(): void
    {
        $createCountry = $this->app->make(CreateCountryAction::class);
        $createLevel = $this->app->make(CreateAdministrativeLevelAction::class);
        $createUnit = $this->app->make(CreateAdministrativeUnitAction::class);
        $firstCountry = $createCountry->handle('GH', 'Ghana');
        $secondCountry = $createCountry->handle('UG', 'Uganda');
        $secondLevel = $createLevel->handle($secondCountry, 'region', 'Region', 10);
        $foreignUnit = $createUnit->handle($secondCountry, $secondLevel, 'Eastern Region');
        $auditCount = AuditEvent::query()->count();
        $wasRejected = false;

        try {
            $this->app->make(CreateLocationAction::class)->handle(new LocationData(
                country: $firstCountry,
                name: 'Invalid Location',
                timezone: 'Africa/Accra',
                administrativeUnit: $foreignUnit,
            ));
            $this->fail('Expected the cross-country location to be rejected.');
        } catch (InvalidArgumentException) {
            $wasRejected = true;
        }

        $this->assertTrue($wasRejected);
        $this->assertSame(0, Location::query()->count());
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    /**
     * @return array{Country, AdministrativeLevel, AdministrativeLevel, AdministrativeLevel}
     */
    private function createLevels(): array
    {
        $country = $this->app->make(CreateCountryAction::class)->handle('GH', 'Ghana');
        $createLevel = $this->app->make(CreateAdministrativeLevelAction::class);
        $regionLevel = $createLevel->handle($country, 'region', 'Region', 10);
        $districtLevel = $createLevel->handle($country, 'district', 'District', 20);
        $wardLevel = $createLevel->handle($country, 'ward', 'Ward', 30);

        return [$country, $regionLevel, $districtLevel, $wardLevel];
    }
}
