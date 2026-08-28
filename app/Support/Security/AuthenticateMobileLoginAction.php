<?php

namespace App\Support\Security;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

            if (
                $user === null
                || $user->isSuspended()
                || $user->email_verified_at === null
                || ! Hash::check($password, $user->password)
            ) {
                throw new AuthenticationException;
            }

            $device = $this->registerDevice->handle($user, $deviceData, $user);
            $securitySession = $this->recordSecuritySession->handle(
                user: $user,
                device: $device,
                expiresAt: now()->utc()->addSeconds(
                    min(max((int) config('api.mobile.refresh_ttl_seconds'), 3600), 2_592_000),
                ),
                actor: $user,
            );

            return $this->issueCredentials->handle($user, $device, $securitySession);
        }, attempts: 3);
    }
}
