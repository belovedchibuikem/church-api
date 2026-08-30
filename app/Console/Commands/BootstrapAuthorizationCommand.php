<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Authorization\ProvisionAuthorizationBundlesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'authorization:bootstrap')]
#[Signature('authorization:bootstrap {email? : Admin user email (defaults to SUPER_ADMIN_EMAIL env)} {--force : Run in production without confirmation}')]
#[Description('Install all roles/permissions and grant the super administrator role to a user')]
class BootstrapAuthorizationCommand extends Command
{
    use ConfirmableTrait;

    public function handle(ProvisionAuthorizationBundlesAction $provision): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $result = $provision->handle();
        $this->components->info(sprintf(
            'Authorization foundation: %d roles, %d permissions, %d role-permission grants.',
            $result['roles'],
            $result['permissions'],
            $result['grants'],
        ));

        $email = strtolower(trim((string) ($this->argument('email') ?: env('SUPER_ADMIN_EMAIL', ''))));
        if ($email === '') {
            $this->components->warn('No email provided. Pass an email argument or set SUPER_ADMIN_EMAIL in .env.');
            $this->components->info('Roles and permissions are installed. Grant access with: php artisan authorization:grant-super-admin user@example.com');

            return self::SUCCESS;
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->doesntExist()) {
            $this->components->error("No user found with email {$email}. Create the account first, then re-run this command.");

            return self::FAILURE;
        }

        /** @var GrantSuperAdminCommand $grant */
        $grant = app(GrantSuperAdminCommand::class);

        return $grant->grantByEmail($email, $this->output);
    }
}