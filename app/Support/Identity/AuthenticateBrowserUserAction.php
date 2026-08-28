<?php

namespace App\Support\Identity;

use App\Identity\UserAccountStatus;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateBrowserUserAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        string $email,
        #[\SensitiveParameter] string $password,
        bool $remember,
    ): User {
        $email = mb_strtolower(trim($email));
        $user = User::query()->where('email', $email)->first();
        $passwordHash = $user?->getAuthPassword();
        $credentialsMatch = is_string($passwordHash)
            && $passwordHash !== ''
            && Hash::check($password, $passwordHash);

        if (
            $user === null
            || ! $credentialsMatch
            || $user->account_status !== UserAccountStatus::Active
            || $user->isSuspended()
            || $user->suspended_at !== null
        ) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        Auth::guard('web')->login($user, $remember);
        $user->load('person.profile');

        $this->recordAuditEvent->handle(new AuditEventData(
            action: 'identity.browser.logged_in',
            actor: $user,
            targetType: 'user',
            targetId: (string) $user->getKey(),
        ));

        return $user;
    }
}
