<?php

namespace App\Console\Commands;

use App\Support\Authorization\ProvisionAuthorizationBundlesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

#[Signature('authorization:install-foundation {--force : Run in production without confirmation}')]
#[Description('Install the explicit permission and role bundles without assigning any user')]
class InstallAuthorizationFoundation extends Command
{
    use ConfirmableTrait;

    /**
     * Execute the console command.
     */
    public function handle(ProvisionAuthorizationBundlesAction $provision): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $result = $provision->handle();

        $this->components->info(sprintf(
            'Authorization foundation installed: %d roles, %d permissions, %d new grants, zero user assignments.',
            $result['roles'],
            $result['permissions'],
            $result['grants'],
        ));

        return self::SUCCESS;
    }
}
