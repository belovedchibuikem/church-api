<?php

$states = json_decode(file_get_contents(__DIR__.'/../database/data/geography/world-states.json'), true);
$countriesPath = __DIR__.'/../database/data/geography/countries.json';
$countries = json_decode((string) file_get_contents($countriesPath), true);
if (! is_array($countries)) {
    $countries = [];
}
foreach ($states as $iso => $row) {
    if (! isset($countries[$iso]) && ! empty($row['name'])) {
        $countries[$iso] = $row['name'];
    }
}
ksort($countries);
file_put_contents($countriesPath, json_encode($countries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");

$ng = json_decode(file_get_contents(__DIR__.'/../database/data/geography/localities-NG.json'), true);
$localityCount = 0;
foreach (($ng['states'] ?? []) as $children) {
    $localityCount += count($children);
}

echo 'countries='.count($countries).PHP_EOL;
echo 'states_countries='.count($states).PHP_EOL;
echo 'ng_states='.count($ng['states'] ?? []).PHP_EOL;
echo 'ng_localities='.$localityCount.PHP_EOL;
