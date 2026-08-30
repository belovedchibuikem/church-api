<?php

namespace App\Support\Security;

use App\Identity\UserAccountStatus;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticateMobileLoginAction
{
    public function __construct(
        private RegisterDeviceAction $registerDevice,
        private RecordSecuritySessionAction $recordSecuritySession,
        private IssueMobileCredentialsAction $issueCredentials,
    ) {}

    public function handle(
        string $email,
        string $password,
        RegisterDeviceData $deviceData,
    ): IssuedMobileCredentials {
        return DB::transaction(function () use ($email, $password, $deviceData): IssuedMobileCredentials {
            $user = User::query()
                ->where('email', Str::lower(trim($email)))
                ->lockForUpdate()
                ->first();

            $passwordHash = $user?->getAuthPassword();
            $credentialsMatch = is_string($passwordHash)
                && $passwordHash !== ''
                && Hash::check($password, $passwordHash);

            if ($user === null || ! $credentialsMatch) {
                throw new AuthenticationException;
            }

            if (
                $user->account_status !== UserAccountStatus::Active
                || $user->isSuspended()
                || $user->suspended_at !== null
            ) {
                throw new AuthenticationException;
            }

            if ($user->email_verified_at === null) {
                throw ValidationException::withMessages([
                    'email' => [
                        'Verify your email address before using the mobile app. '
                        .'Open the verification link sent to your inbox, then try again.',
                    ],
                ]);
            }

            $device = $this->registerDevice->handle($user, $deviceData, $user);
            // Null security-session expiry keeps the device signed in until logout;
            // access/refresh token TTLs still rotate via MobileCredentialIssuer.
            $securitySession = $this->recordSecuritySession->handle(
                user: $user,
                device: $device,
                expiresAt: null,
                actor: $user,
            );

            return $this->issueCredentials->handle($user, $device, $securitySession);
        }, attempts: 3);
    }
}
