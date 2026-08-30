<?php

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'countries='.Country::query()->count().PHP_EOL;
echo 'levels='.AdministrativeLevel::query()->count().PHP_EOL;
echo 'units='.AdministrativeUnit::query()->count().PHP_EOL;
foreach (['NG', 'GH', 'KE', 'US', 'IN', 'PH'] as $iso) {
    $count = AdministrativeUnit::query()
        ->whereHas('country', static fn ($q) => $q->where('iso_code', $iso))
        ->count();
    echo "units_{$iso}={$count}".PHP_EOL;
}
