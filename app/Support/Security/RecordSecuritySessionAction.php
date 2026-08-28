<?php

namespace App\Support\Security;

use App\Exceptions\DeviceOwnershipException;
use App\Exceptions\RevokedDeviceException;
use App\Exceptions\SuspendedAccountException;
use App\Models\Device;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordSecuritySessionAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        User $user,
        ?Device $device = null,
        ?DateTimeInterface $expiresAt = null,
        ?User $actor = null,
    ): SecuritySession {
        if ($expiresAt !== null && $expiresAt <= now()) {
            throw new InvalidArgumentException('The security session expiration must be in the future.');
        }

        return DB::transaction(function () use ($user, $device, $expiresAt, $actor): SecuritySession {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->isSuspended()) {
                throw new SuspendedAccountException;
            }

            $lockedDevice = $this->lockUsableDevice($lockedUser, $device);
            $startedAt = now()->utc();
            $securitySession = SecuritySession::query()->create([
                'user_id' => $lockedUser->getKey(),
                'device_id' => $lockedDevice?->getKey(),
                'started_at' => $startedAt,
                'last_seen_at' => $startedAt,
                'expires_at' => $expiresAt === null ? null : Carbon::instance($expiresAt)->utc(),
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'security.session.recorded',
                actor: $actor,
                targetType: 'security_session',
                targetId: $securitySession->public_id,
                metadata: [
                    'device_public_id' => $lockedDevice?->public_id,
                    'expires_at' => $securitySession->expires_at?->toIso8601String(),
                ],
            ));

            return $securitySession;
        }, attempts: 3);
    }

    private function lockUsableDevice(User $user, ?Device $device): ?Device
    {
        if ($device === null) {
            return null;
        }

        $lockedDevice = Device::query()->lockForUpdate()->findOrFail($device->getKey());

        if ($lockedDevice->user_id !== $user->getKey()) {
            throw new DeviceOwnershipException;
        }

        if ($lockedDevice->revoked_at !== null) {
            throw new RevokedDeviceException;
        }

        return $lockedDevice;
    }
}
