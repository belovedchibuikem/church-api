<?php

namespace App\Console\Commands;

use App\Demo\SeedDemoDatasetAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

#[Signature('demo:seed {--force : Replace an existing demo dataset}')]
#[Description('Seed demonstration churches, events, people, CMS copy, and images')]
class SeedDemoDatasetCommand extends Command
{
    use ConfirmableTrait;

    public function handle(SeedDemoDatasetAction $seed): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $result = $seed->handle((bool) $this->option('force'));
        if ($result['skipped']) {
            $this->components->warn('Demo dataset already exists. Re-run with --force to replace it.');

            return self::SUCCESS;
        }

        $this->components->info('Demo dataset is ready.');
        $this->line('  Admin:  admin@familyhouse.demo');
        $this->line('  Pastor: pastor@familyhouse.demo');
        $this->line('  Member: member@familyhouse.demo');
        $this->line('  Password for all demo accounts: DemoPass!2026');

        return self::SUCCESS;
    }
}
