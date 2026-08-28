<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Crusade;
use App\Models\Location;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PublicMissionApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_lists_only_upcoming_crusades_by_default_with_public_safe_fields(): void
    {
        $country = Country::factory()->create(['iso_code' => 'NG']);
        $location = Location::factory()->for($country)->create([
            'name' => 'Lagos Mission Ground',
            'address_line_one' => 'Confidential Street Detail',
            'postal_code' => '100001',
        ]);
        $upcoming = Crusade::factory()->published()->for($location)->create([
            'name' => 'Lagos Hope Crusade',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
        Crusade::factory()->published()->for($location)->create([
            'name' => 'Past Crusade',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subDays(4),
        ]);
        Crusade::factory()->for($location)->create([
            'name' => 'Internal Draft Crusade',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
        ]);

        $response = $this->getJson('/api/v1/mission/crusades?country=ng');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $upcoming->public_id)
            ->assertJsonPath('data.0.location.country.code', 'NG')
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'name',
                    'starts_at',
                    'ends_at',
                    'location' => ['id', 'name', 'locality', 'timezone', 'coordinates', 'country' => ['code', 'name']],
                ]],
                'meta' => ['api_version', 'timestamp', 'pagination' => ['current_page', 'last_page', 'per_page', 'total']],
                'correlation_id',
            ])
            ->assertJsonMissingPath('data.0.database_id')
            ->assertJsonMissingPath('data.0.location.address_line_one')
            ->assertJsonMissingPath('data.0.location.postal_code');
    }

    public function test_it_can_filter_and_sort_the_public_crusade_catalogue(): void
    {
        $location = Location::factory()->create();
        Crusade::factory()->published()->for($location)->create([
            'name' => 'Alpha Mission',
            'starts_at' => '2026-09-10 09:00:00',
            'ends_at' => '2026-09-11 09:00:00',
        ]);
        Crusade::factory()->published()->for($location)->create([
            'name' => 'Zion Mission',
            'starts_at' => '2026-10-10 09:00:00',
            'ends_at' => '2026-10-11 09:00:00',
        ]);

        $response = $this->getJson('/api/v1/mission/crusades?status=all&starts_from=2026-09-01&starts_until=2026-12-01&sort=-name&per_page=1');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Zion Mission')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_it_returns_a_minimized_crusade_detail_and_normalized_not_found_error(): void
    {
        $crusade = Crusade::factory()->published()->for(Location::factory())->create();

        $this->getJson('/api/v1/mission/crusades/'.$crusade->public_id)
            ->assertOk()
            ->assertJsonPath('data.id', $crusade->public_id)
            ->assertJsonMissingPath('data.database_id')
            ->assertJsonMissingPath('data.location.address_line_one');

        $this->getJson('/api/v1/mission/crusades/01AAAAAAAAAAAAAAAAAAAAAAAA')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonMissingPath('exception');

        $unpublished = Crusade::factory()->for(Location::factory())->create();
        $this->getJson('/api/v1/mission/crusades/'.$unpublished->public_id)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_it_lists_only_locations_with_matching_mission_activity(): void
    {
        $activeLocation = Location::factory()->create(['name' => 'Active Mission Field']);
        $inactiveLocation = Location::factory()->create(['name' => 'No Mission Field']);
        Crusade::factory()->published()->for($activeLocation)->create([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $response = $this->getJson('/api/v1/mission/locations?q=Active');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeLocation->public_id)
            ->assertJsonMissing(['id' => $inactiveLocation->public_id])
            ->assertJsonMissingPath('data.0.address_line_one');
    }

    public function test_it_rejects_invalid_or_unknown_catalogue_query_parameters(): void
    {
        $this->getJson('/api/v1/mission/crusades?per_page=51&internal=true')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['per_page', 'internal']]]]);

        $this->getJson('/api/v1/mission/locations?status=secret')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['status']]]]);
    }
}
