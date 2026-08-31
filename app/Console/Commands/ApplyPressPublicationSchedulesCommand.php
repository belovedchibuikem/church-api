<?php

namespace App\Console\Commands;

use App\Support\Press\ApplyPressPublicationSchedulesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('press:apply-schedules')]
#[Description('Publish or unpublish Press publications whose scheduled times have elapsed')]
class ApplyPressPublicationSchedulesCommand extends Command
{
    public function handle(ApplyPressPublicationSchedulesAction $action): int
    {
        $applied = $action->handle();
        $this->components->info("Applied {$applied} Press schedule action(s).");

        return self::SUCCESS;
    }
}
