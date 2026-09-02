<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

config(['database.connections.mysql.database' => 'family_house_connect_testing']);
DB::purge('mysql');

$exitCode = $kernel->call('migrate', ['--force' => true]);

echo DB::connection()->getDatabaseName().PHP_EOL;
echo trim($kernel->output()).PHP_EOL;

exit($exitCode);
