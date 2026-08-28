<?php

namespace App\Support\Identity;

use App\Identity\UserAccountStatus;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class VerifyEmailAddressAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(int $userId, string $emailHash): User
    {
        $wasVerified = false;

        $user = DB::transaction(function () use ($userId, $emailHash, &$wasVerified): User {
            $user = User::query()
                ->whereKey($userId)
                ->where('account_status', UserAccountStatus::Active->value)
                ->whereNull('suspended_at')
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals(sha1($user->getEmailForVerification()), $emailHash)) {
                throw (new ModelNotFoundException)->setModel(User::class);
            }

            if ($user->hasVerifiedEmail()) {
                return $user;
            }

            $user->markEmailAsVerified();
            $wasVerified = true;

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'identity.email.verified',
                actor: $user,
                targetType: 'user',
                targetId: (string) $user->getKey(),
            ));

            return $user;
        }, attempts: 3);

        if ($wasVerified) {
            event(new Verified($user));
        }

        return $user;
    }
}
