<?php

namespace App\Support\Organization;

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Populates countries → administrative levels → states → local governments.
 *
 * Data files live under database/data/geography/ (see scripts/download_geography_datasets.php).
 */
final class SeedWorldGeographyAction
{
    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}> level codes/names */
    private const LEVEL_PROFILES = [
        'NG' => ['state', 'State', 'local_government', 'Local Government'],
        'GH' => ['region', 'Region', 'district', 'District'],
        'KE' => ['county', 'County', 'sub_county', 'Sub-County'],
        'US' => ['state', 'State', 'county', 'County'],
        'GB' => ['country_constituent', 'Country / Nation', 'local_authority', 'Local Authority'],
        'CA' => ['province', 'Province / Territory', 'census_division', 'Census Division'],
        'IN' => ['state', 'State / Union Territory', 'district', 'District'],
        'PH' => ['province', 'Province', 'city_municipality', 'City / Municipality'],
        'ZA' => ['province', 'Province', 'municipality', 'Municipality'],
        'UG' => ['district', 'District', 'county', 'County'],
        'TZ' => ['region', 'Region', 'district', 'District'],
        'CM' => ['region', 'Region', 'department', 'Department'],
    ];

    /**
     * @param  list<string>|null  $onlyIsos  Limit to these ISO codes (uppercase). Null = all countries in dataset.
     * @param  bool  $withLocalities  Also import second-level units when locality files exist.
     * @return array{countries: int, levels: int, states: int, localities: int}
     */
    public function handle(
        ?array $onlyIsos = null,
        bool $withLocalities = true,
        ?callable $onProgress = null,
    ): array {
        $countries = $this->loadCountries();
        $worldStates = $this->loadWorldStates();

        if ($onlyIsos !== null) {
            $only = array_fill_keys(array_map('strtoupper', $onlyIsos), true);
            $countries = array_intersect_key($countries, $only);
            $worldStates = array_intersect_key($worldStates, $only);
        }

        $counts = ['countries' => 0, 'levels' => 0, 'states' => 0, 'localities' => 0];

        foreach ($countries as $iso => $name) {
            $country = Country::query()->firstOrCreate(
                ['iso_code' => $iso],
                ['name' => $name],
            );
            if ($country->name !== $name) {
                $country->forceFill(['name' => $name])->save();
            }
            $counts['countries']++;

            [$stateCode, $stateLabel, $localCode, $localLabel] = $this->levelProfile($iso);
            $stateLevel = AdministrativeLevel::query()->firstOrCreate(
                ['country_id' => $country->getKey(), 'code' => $stateCode],
                ['name' => $stateLabel, 'sort_order' => 10],
            );
            $localLevel = AdministrativeLevel::query()->firstOrCreate(
                ['country_id' => $country->getKey(), 'code' => $localCode],
                ['name' => $localLabel, 'sort_order' => 20],
            );
            $levelsCreated = ($stateLevel->wasRecentlyCreated ? 1 : 0) + ($localLevel->wasRecentlyCreated ? 1 : 0);
            $counts['levels'] += $levelsCreated;

            $statesCreated = 0;
            $localitiesCreated = 0;
            $stateUnits = [];
            $stateNames = $worldStates[$iso]['states'] ?? [];

            foreach ($stateNames as $stateName) {
                $display = $this->displayName($stateName);
                $existing = AdministrativeUnit::query()
                    ->whereBelongsTo($country)
                    ->where('administrative_level_id', $stateLevel->getKey())
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($display)])
                    ->first();

                if ($existing !== null) {
                    $stateUnits[$stateName] = $existing;
                    continue;
                }

                $reference = $this->referenceCode($iso, $stateName);
                $unit = AdministrativeUnit::query()->firstOrCreate(
                    ['country_id' => $country->getKey(), 'reference_code' => $reference],
                    [
                        'administrative_level_id' => $stateLevel->getKey(),
                        'parent_id' => null,
                        'name' => $display,
                    ],
                );
                if ($unit->wasRecentlyCreated) {
                    $statesCreated++;
                }
                $stateUnits[$stateName] = $unit;
            }

            if ($withLocalities) {
                $localities = $this->loadLocalities($iso);
                foreach (($localities['states'] ?? []) as $stateName => $children) {
                    $parent = $stateUnits[$stateName]
                        ?? $this->findStateUnit($country, $stateLevel, (string) $stateName);
                    if ($parent === null) {
                        continue;
                    }
                    foreach ($children as $childName) {
                        $childName = trim((string) $childName);
                        if ($childName === '') {
                            continue;
                        }
                        $display = $this->displayName($childName);
                        $existing = AdministrativeUnit::query()
                            ->whereBelongsTo($country)
                            ->where('administrative_level_id', $localLevel->getKey())
                            ->where('parent_id', $parent->getKey())
                            ->whereRaw('LOWER(name) = ?', [mb_strtolower($display)])
                            ->first();
                        if ($existing !== null) {
                            continue;
                        }
                        $reference = $this->referenceCode($iso, $stateName, $childName);
                        $unit = AdministrativeUnit::query()->firstOrCreate(
                            ['country_id' => $country->getKey(), 'reference_code' => $reference],
                            [
                                'administrative_level_id' => $localLevel->getKey(),
                                'parent_id' => $parent->getKey(),
                                'name' => $display,
                            ],
                        );
                        if ($unit->wasRecentlyCreated) {
                            $localitiesCreated++;
                        }
                    }
                }
            }

            $result = [
                'country' => 1,
                'levels' => $levelsCreated,
                'states' => $statesCreated,
                'localities' => $localitiesCreated,
            ];
            $counts['states'] += $statesCreated;
            $counts['localities'] += $localitiesCreated;

            if ($onProgress !== null) {
                $onProgress($iso, $name, $result);
            }
        }

        return $counts;
    }

    /** @return array<string, string> iso => name */
    private function loadCountries(): array
    {
        $path = database_path('data/geography/countries.json');
        $fromFile = $this->readJsonObject($path);
        $countries = [];
        foreach ($fromFile as $iso => $name) {
            $iso = strtoupper(trim((string) $iso));
            $name = trim((string) $name);
            if (strlen($iso) === 2 && $name !== '') {
                try {
                    new IsoCountryCode($iso);
                } catch (\InvalidArgumentException) {
                    continue;
                }
                $countries[$iso] = $name;
            }
        }

        // Fallback / merge from world-states when countries.json is empty or incomplete.
        $worldStates = $this->loadWorldStates();
        foreach ($worldStates as $iso => $row) {
            $iso = strtoupper((string) $iso);
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || isset($countries[$iso])) {
                continue;
            }
            try {
                new IsoCountryCode($iso);
            } catch (\InvalidArgumentException) {
                continue;
            }
            $countries[$iso] = $name;
        }

        if ($countries === []) {
            throw new RuntimeException(
                'No geography country dataset found. Run: php scripts/download_geography_datasets.php',
            );
        }

        ksort($countries);

        return $countries;
    }

    /** @return array<string, array{name: string, states: list<string>}> */
    private function loadWorldStates(): array
    {
        $path = database_path('data/geography/world-states.json');
        if (! is_file($path)) {
            return [];
        }
        $decoded = $this->readJsonObject($path);
        $out = [];
        foreach ($decoded as $iso => $row) {
            if (! is_array($row)) {
                continue;
            }
            $iso = strtoupper(trim((string) $iso));
            $name = trim((string) ($row['name'] ?? ''));
            $states = [];
            foreach (($row['states'] ?? []) as $state) {
                $stateName = trim((string) $state);
                if ($stateName !== '') {
                    $states[] = $stateName;
                }
            }
            $out[$iso] = ['name' => $name, 'states' => $states];
        }

        return $out;
    }

    /** @return array{iso?: string, name?: string, states?: array<string, list<string>>} */
    private function loadLocalities(string $iso): array
    {
        $iso = strtoupper($iso);
        $path = database_path('data/geography/localities-'.$iso.'.json');
        if (! is_file($path)) {
            $priority = database_path('data/geography/localities-priority.json');
            if (! is_file($priority)) {
                return [];
            }
            $all = $this->readJsonObject($priority);
            $row = $all[$iso] ?? [];

            return is_array($row) ? $row : [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array{0: string, 1: string, 2: string, 3: string} */
    private function levelProfile(string $iso): array
    {
        return self::LEVEL_PROFILES[$iso]
            ?? ['state', 'State / Region', 'local_government', 'Local Government / City'];
    }

    private function displayName(string $name): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        // CountriesNow often suffixes " State" / " Province"; keep short display names.
        $normalized = preg_replace(
            '/\s+(State|Province|Region|County|Territory|District)$/i',
            '',
            $normalized,
        ) ?? $normalized;

        return trim($normalized);
    }

    private function referenceCode(string $iso, string $state, ?string $locality = null): string
    {
        $parts = [$iso, $this->slugPart($state)];
        if ($locality !== null) {
            $parts[] = $this->slugPart($locality);
        }
        $code = Str::upper(implode('-', $parts));
        if (strlen($code) > 64) {
            $code = substr($code, 0, 64);
        }

        return $code;
    }

    private function slugPart(string $value): string
    {
        $slug = Str::upper(Str::slug($value, '_'));
        $slug = preg_replace('/[^A-Z0-9_]/', '', $slug) ?? $slug;

        return $slug !== '' ? $slug : 'X';
    }

    private function findStateUnit(
        Country $country,
        AdministrativeLevel $stateLevel,
        string $stateName,
    ): ?AdministrativeUnit {
        return AdministrativeUnit::query()
            ->whereBelongsTo($country)
            ->whereBelongsTo($stateLevel, 'administrativeLevel')
            ->where('name', $this->displayName($stateName))
            ->first();
    }

    /** @return array<string, mixed> */
    private function readJsonObject(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
