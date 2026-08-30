<?php

namespace App\Console\Commands;

use App\Support\Organization\SeedWorldGeographyAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('geography:seed
    {--country=* : Limit to one or more ISO country codes (e.g. NG,GH)}
    {--states-only : Seed countries and first-level units only (skip LGAs/cities)}
    {--download : Refresh geography JSON datasets before seeding}')]
#[Description('Populate countries, states/regions, and local governments into organization geography tables')]
class SeedWorldGeographyCommand extends Command
{
    public function handle(SeedWorldGeographyAction $seed): int
    {
        if ($this->option('download')) {
            $script = base_path('scripts/download_geography_datasets.php');
            if (! is_file($script)) {
                $this->components->error('Missing scripts/download_geography_datasets.php');

                return self::FAILURE;
            }
            $this->components->info('Refreshing geography datasets…');
            passthru('php '.escapeshellarg($script), $exitCode);
            if ($exitCode !== 0) {
                $this->components->error('Dataset download failed.');

                return self::FAILURE;
            }
        }

        $countries = array_values(array_filter(array_map(
            static fn ($value): string => strtoupper(trim((string) $value)),
            (array) $this->option('country'),
        )));

        $this->components->info('Seeding world geography into organization tables…');

        $counts = $seed->handle(
            onlyIsos: $countries === [] ? null : $countries,
            withLocalities: ! (bool) $this->option('states-only'),
            onProgress: function (string $iso, string $name, array $result): void {
                $this->line(sprintf(
                    '  %s %-24s states +%d localities +%d',
                    $iso,
                    $name,
                    $result['states'],
                    $result['localities'],
                ));
            },
        );

        $this->newLine();
        $this->components->info('Geography seed complete.');
        $this->line('  Countries touched: '.$counts['countries']);
        $this->line('  Levels created:    '.$counts['levels']);
        $this->line('  States created:    '.$counts['states']);
        $this->line('  Localities created:'.$counts['localities']);

        return self::SUCCESS;
    }
}
