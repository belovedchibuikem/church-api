<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\Country;
use App\Models\Location;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ChurchControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_returns_paginated_public_safe_churches_with_allowlisted_filters_and_sorting(): void
    {
        [$unit, $location] = $this->locationInCountry('GH', 'Accra');
        Church::factory()->published()->create([
            'administrative_unit_id' => $unit->getKey(),
            'location_id' => $location->getKey(),
            'name' => 'Alpha Assembly',
        ]);
        Church::factory()->published()->create([
            'administrative_unit_id' => $unit->getKey(),
            'location_id' => $location->getKey(),
            'name' => 'Beta Assembly',
        ]);
        Church::factory()->create([
            'administrative_unit_id' => $unit->getKey(),
            'location_id' => $location->getKey(),
            'name' => 'Alpha Internal',
        ]);

        $response = $this->getJson('/api/v1/churches?filter[name]=Alpha&filter[country]=gh&sort=-name&per_page=10');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Alpha Assembly')
            ->assertJsonPath('data.items.0.location.locality', 'Accra')
            ->assertJsonPath('data.items.0.location.country.code', 'GH')
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonMissingPath('data.items.0.location.address_line_one')
            ->assertJsonMissingPath('data.items.0.location.latitude')
            ->assertJsonMissingPath('data.items.0.administrative_unit_id');
    }

    public function test_returns_only_published_church_by_ulid_and_hides_unpublished_records(): void
    {
        $published = Church::factory()->published()->create(['name' => 'Public Assembly']);
        $unpublished = Church::factory()->create(['name' => 'Internal Assembly']);

        $this->getJson("/api/v1/churches/{$published->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $published->public_id)
            ->assertJsonPath('data.name', 'Public Assembly')
            ->assertJsonPath('data.home_churches', [])
            ->assertJsonMissingPath('data.location.address_line_one');

        $this->getJson("/api/v1/churches/{$unpublished->public_id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        $this->getJson('/api/v1/churches/123')->assertNotFound();
    }

    public function test_returns_422_for_unknown_filters_sorts_and_wildcard_input_is_literal(): void
    {
        Church::factory()->published()->create(['name' => 'Alpha Assembly']);

        $this->getJson('/api/v1/churches?unknown=value')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->getJson('/api/v1/churches?filter[secret]=value')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->getJson('/api/v1/churches?sort=contact_email')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->getJson('/api/v1/churches?filter[name]=Alpha%25')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    /** @return array{AdministrativeUnit, Location} */
    private function locationInCountry(string $isoCode, string $locality): array
    {
        $country = Country::factory()->create(['iso_code' => $isoCode]);
        $level = AdministrativeLevel::factory()->create(['country_id' => $country->getKey()]);
        $unit = AdministrativeUnit::factory()->create([
            'country_id' => $country->getKey(),
            'administrative_level_id' => $level->getKey(),
        ]);
        $location = Location::factory()->create([
            'country_id' => $country->getKey(),
            'administrative_unit_id' => $unit->getKey(),
            'locality' => $locality,
        ]);

        return [$unit, $location];
    }
}
