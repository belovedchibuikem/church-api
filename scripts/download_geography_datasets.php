<?php

declare(strict_types=1);

/**
 * Downloads geography datasets used by `php artisan geography:seed`.
 *
 * Sources:
 * - restcountries.com → ISO country names
 * - countriesnow.space → states/provinces and cities/LGAs
 */

$dir = dirname(__DIR__).'/database/data/geography';
if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
    fwrite(STDERR, "Unable to create {$dir}\n");
    exit(1);
}

function httpGet(string $url): string
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\nUser-Agent: FamilyHouseGeographySeeder/1.0\r\n",
            'timeout' => 60,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        throw new RuntimeException("GET failed: {$url}");
    }

    return $raw;
}

function httpPostJson(string $url, array $body): string
{
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\nUser-Agent: FamilyHouseGeographySeeder/1.0\r\n",
            'content' => json_encode($body, JSON_THROW_ON_ERROR),
            'timeout' => 60,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        throw new RuntimeException('POST failed: '.$url.' '.json_encode($body));
    }

    return $raw;
}

echo "Fetching countries...\n";
$countries = [];
try {
    $countriesPayload = json_decode(httpGet('https://restcountries.com/v3.1/all?fields=name,cca2'), true);
    if (is_array($countriesPayload)) {
        foreach ($countriesPayload as $row) {
            $code = strtoupper(trim((string) ($row['cca2'] ?? '')));
            $name = trim((string) ($row['name']['common'] ?? ''));
            if (strlen($code) === 2 && $name !== '') {
                $countries[$code] = $name;
            }
        }
    }
} catch (Throwable $e) {
    echo 'restcountries failed: '.$e->getMessage().PHP_EOL;
}

echo "Fetching world states...\n";
$statesPayload = json_decode(httpGet('https://countriesnow.space/api/v0.1/countries/states'), true);
$worldStates = [];
foreach (($statesPayload['data'] ?? []) as $row) {
    if (! is_array($row)) {
        continue;
    }
    $iso = strtoupper(trim((string) ($row['iso2'] ?? '')));
    $name = trim((string) ($row['name'] ?? ''));
    if (strlen($iso) !== 2 || $name === '') {
        continue;
    }
    $states = [];
    foreach (($row['states'] ?? []) as $state) {
        $stateName = trim((string) ($state['name'] ?? ''));
        if ($stateName !== '') {
            $states[] = $stateName;
        }
    }
    $states = array_values(array_unique($states));
    sort($states);
    $worldStates[$iso] = [
        'name' => $name,
        'states' => $states,
    ];
}
ksort($worldStates);
file_put_contents(
    $dir.'/world-states.json',
    json_encode($worldStates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n",
);
echo 'countries_with_states='.count($worldStates).PHP_EOL;

foreach ($worldStates as $iso => $row) {
    if (! isset($countries[$iso]) && ($row['name'] ?? '') !== '') {
        $countries[$iso] = $row['name'];
    }
}
ksort($countries);
file_put_contents(
    $dir.'/countries.json',
    json_encode($countries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n",
);
echo 'countries='.count($countries).PHP_EOL;

$priority = ['NG', 'GH', 'KE', 'ZA', 'UG', 'TZ', 'CM', 'US', 'GB', 'CA', 'IN', 'PH'];
$localities = [];

foreach ($priority as $iso) {
    $entry = $worldStates[$iso] ?? null;
    if ($entry === null) {
        echo "skip {$iso}\n";
        continue;
    }
    $countryName = $entry['name'];
    $stateMap = [];
    echo "Fetching localities for {$iso} ({$countryName})...\n";
    foreach ($entry['states'] as $stateName) {
        try {
            $payload = json_decode(
                httpPostJson('https://countriesnow.space/api/v0.1/countries/state/cities', [
                    'country' => $countryName,
                    'state' => $stateName,
                ]),
                true,
            );
            $cities = [];
            foreach (($payload['data'] ?? []) as $city) {
                $cityName = trim((string) $city);
                if ($cityName !== '') {
                    $cities[] = $cityName;
                }
            }
            $cities = array_values(array_unique($cities));
            sort($cities);
            $stateMap[$stateName] = $cities;
            echo "  {$stateName}=".count($cities).PHP_EOL;
        } catch (Throwable $e) {
            echo '  ERROR '.$stateName.': '.$e->getMessage().PHP_EOL;
            $stateMap[$stateName] = [];
        }
        usleep(120_000);
    }
    $localities[$iso] = [
        'iso' => $iso,
        'name' => $countryName,
        'states' => $stateMap,
    ];
    file_put_contents(
        $dir.'/localities-'.$iso.'.json',
        json_encode($localities[$iso], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n",
    );
}

file_put_contents(
    $dir.'/localities-priority.json',
    json_encode($localities, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n",
);

echo "done\n";
