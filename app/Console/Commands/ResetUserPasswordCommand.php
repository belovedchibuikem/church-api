<?php

namespace App\Console\Commands;

use App\Identity\UserAccountStatus;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('user:reset-password {email : User email address} {password? : New password (defaults to DemoPass!2026 for demo accounts)}')]
#[Description('Reset a user password (local troubleshooting)')]
class ResetUserPasswordCommand extends Command
{
    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->components->error('This command is only available in local and testing environments.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $this->components->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        $password = (string) ($this->argument('password') ?: 'DemoPass!2026');

        $user->forceFill([
            'password' => $password,
            'account_status' => UserAccountStatus::Active,
            'suspended_at' => null,
            'suspension_reason' => null,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->components->info("Password reset for {$user->email}.");
        $this->line('Account status: active · email verified: yes');

        return self::SUCCESS;
    }
}
