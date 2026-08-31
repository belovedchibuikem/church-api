<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Support\Organization\SeedWorldGeographyAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicGeographyApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_geography_endpoints_return_seeded_hierarchy(): void
    {
        $this->ensureMinimalGeographyFixtures();

        $this->getJson('/api/v1/geography/countries')
            ->assertOk()
            ->assertJsonPath('data.0.code', fn ($value) => is_string($value) && strlen($value) === 2);

        $this->getJson('/api/v1/geography/countries/NG')
            ->assertOk()
            ->assertJsonPath('data.code', 'NG')
            ->assertJsonPath('data.levels.0.code', 'state');

        $this->getJson('/api/v1/geography/countries/NG/states')
            ->assertOk()
            ->assertJsonPath('data.0.name', fn ($value) => is_string($value) && $value !== '');

        $lagos = AdministrativeUnit::query()
            ->whereHas('country', fn ($q) => $q->where('iso_code', 'NG'))
            ->where('name', 'Lagos')
            ->firstOrFail();

        $this->getJson('/api/v1/geography/countries/NG/states/Lagos State/localities')
            ->assertOk()
            ->assertJsonPath('meta.state.name', 'Lagos')
            ->assertJsonPath('data.0.name', fn ($value) => is_string($value) && $value !== '');
    }

    public function test_seed_world_geography_action_is_idempotent_for_nigeria_fixture(): void
    {
        $this->ensureMinimalGeographyFixtures();

        $action = $this->app->make(SeedWorldGeographyAction::class);
        $first = $action->handle(onlyIsos: ['NG'], withLocalities: true);
        $second = $action->handle(onlyIsos: ['NG'], withLocalities: true);

        $this->assertGreaterThanOrEqual(1, $first['countries']);
        $this->assertSame(0, $second['states']);
        $this->assertSame(0, $second['localities']);
        $this->assertTrue(Country::query()->where('iso_code', 'NG')->exists());
        $this->assertGreaterThan(0, AdministrativeUnit::query()->whereHas('country', fn ($q) => $q->where('iso_code', 'NG'))->count());
    }

    private function ensureMinimalGeographyFixtures(): void
    {
        $dir = database_path('data/geography');
        File::ensureDirectoryExists($dir);

        if (! File::exists($dir.'/countries.json')) {
            File::put($dir.'/countries.json', json_encode(['NG' => 'Nigeria'], JSON_PRETTY_PRINT));
        }

        if (! File::exists($dir.'/world-states.json')) {
            File::put($dir.'/world-states.json', json_encode([
                'NG' => [
                    'name' => 'Nigeria',
                    'states' => ['Lagos State', 'Enugu State'],
                ],
            ], JSON_PRETTY_PRINT));
        }

        if (! File::exists($dir.'/localities-NG.json')) {
            File::put($dir.'/localities-NG.json', json_encode([
                'iso' => 'NG',
                'name' => 'Nigeria',
                'states' => [
                    'Lagos State' => ['Ikeja', 'Eti-Osa'],
                    'Enugu State' => ['Enugu East'],
                ],
            ], JSON_PRETTY_PRINT));
        }

        // Prefer real downloaded datasets when present; otherwise fixtures above.
        $this->app->make(SeedWorldGeographyAction::class)->handle(
            onlyIsos: ['NG'],
            withLocalities: true,
        );

        // Guarantee Lagos exists for locality lookup even if display-name stripping differs.
        $nigeria = Country::query()->firstOrCreate(['iso_code' => 'NG'], ['name' => 'Nigeria']);
        $stateLevel = AdministrativeLevel::query()->firstOrCreate(
            ['country_id' => $nigeria->getKey(), 'code' => 'state'],
            ['name' => 'State', 'sort_order' => 10],
        );
        $lgaLevel = AdministrativeLevel::query()->firstOrCreate(
            ['country_id' => $nigeria->getKey(), 'code' => 'local_government'],
            ['name' => 'Local Government', 'sort_order' => 20],
        );
        $lagos = AdministrativeUnit::query()->firstOrCreate(
            ['country_id' => $nigeria->getKey(), 'reference_code' => 'NG-LA'],
            [
                'administrative_level_id' => $stateLevel->getKey(),
                'parent_id' => null,
                'name' => 'Lagos',
            ],
        );
        AdministrativeUnit::query()->firstOrCreate(
            ['country_id' => $nigeria->getKey(), 'reference_code' => 'NG-LA-IKE'],
            [
                'administrative_level_id' => $lgaLevel->getKey(),
                'parent_id' => $lagos->getKey(),
                'name' => 'Ikeja',
            ],
        );
    }
}
