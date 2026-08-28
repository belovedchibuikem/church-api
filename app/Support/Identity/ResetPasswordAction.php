<?php

namespace App\Support\Identity;

use App\Identity\UserAccountStatus;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        string $email,
        #[\SensitiveParameter] string $token,
        #[\SensitiveParameter] string $password,
    ): bool {
        $status = Password::broker()->reset([
            'email' => mb_strtolower(trim($email)),
            'token' => $token,
            'password' => $password,
            'account_status' => UserAccountStatus::Active->value,
            'suspended_at' => null,
        ], function (User $user, string $newPassword): void {
            DB::transaction(function () use ($user, $newPassword): void {
                $user->forceFill([
                    'password' => $newPassword,
                    'remember_token' => Str::random(60),
                ])->save();

                $browserSessionsRevoked = 0;
                if (config('session.driver') === 'database') {
                    $browserSessionsRevoked = DB::table((string) config('session.table'))
                        ->where('user_id', $user->getKey())
                        ->delete();
                }

                $securitySessionsRevoked = SecuritySession::query()
                    ->whereBelongsTo($user)
                    ->whereNull('revoked_at')
                    ->update([
                        'revoked_at' => now()->utc(),
                        'revocation_reason' => 'password.reset',
                    ]);

                $this->recordAuditEvent->handle(new AuditEventData(
                    action: 'identity.password.reset',
                    actor: $user,
                    targetType: 'user',
                    targetId: (string) $user->getKey(),
                    metadata: [
                        'browser_sessions_revoked' => $browserSessionsRevoked,
                        'security_sessions_revoked' => $securitySessionsRevoked,
                    ],
                ));
            }, attempts: 3);

            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET;
    }
}
