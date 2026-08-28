<?php

namespace App\Console\Commands;

use App\Demo\WipeDemoDatasetAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

#[Signature('demo:wipe {--force : Run without confirmation}')]
#[Description('Erase the demonstration dataset and restore baseline CMS pages')]
class WipeDemoDatasetCommand extends Command
{
    use ConfirmableTrait;

    public function handle(WipeDemoDatasetAction $wipe): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $result = $wipe->handle();
        if (! $result['wiped']) {
            $this->components->warn('No demo dataset was present.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'Demo dataset erased (%d records). Baseline CMS pages were restored.',
            $result['deleted_records'],
        ));

        return self::SUCCESS;
    }
}
