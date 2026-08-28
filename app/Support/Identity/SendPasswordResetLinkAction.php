<?php

namespace App\Support\Identity;

use App\Identity\UserAccountStatus;
use Illuminate\Support\Facades\Password;

class SendPasswordResetLinkAction
{
    public function handle(string $email): void
    {
        Password::broker()->sendResetLink([
            'email' => mb_strtolower(trim($email)),
            'account_status' => UserAccountStatus::Active->value,
            'suspended_at' => null,
        ]);
    }
}
