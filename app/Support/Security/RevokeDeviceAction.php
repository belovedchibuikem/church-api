<?php

namespace App\Support\Security;

use App\Models\Device;
use App\Models\MobileAccessToken;
use App\Models\MobileRefreshToken;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RevokeDeviceAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(Device $device, string $reason, ?User $actor = null): Device
    {
        $this->validateReason($reason);

        return DB::transaction(function () use ($device, $reason, $actor): Device {
            $lockedDevice = Device::query()->lockForUpdate()->findOrFail($device->getKey());

            if ($lockedDevice->revoked_at !== null) {
                return $lockedDevice;
            }

            $revokedAt = now()->utc();
            $lockedDevice->forceFill([
                'revoked_at' => $revokedAt,
                'revocation_reason' => $reason,
            ])->save();

            $sessionIds = SecuritySession::query()
                ->whereBelongsTo($lockedDevice)
                ->lockForUpdate()
                ->pluck('id');

            SecuritySession::query()
                ->whereKey($sessionIds)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $revokedAt,
                    'revocation_reason' => $reason,
                    'updated_at' => $revokedAt,
                ]);

            MobileAccessToken::query()
                ->whereBelongsTo($lockedDevice)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $revokedAt,
                    'revocation_reason' => $reason,
                    'updated_at' => $revokedAt,
                ]);

            MobileRefreshToken::query()
                ->whereBelongsTo($lockedDevice)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $revokedAt,
                    'revocation_reason' => $reason,
                    'updated_at' => $revokedAt,
                ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'security.device.revoked',
                actor: $actor,
                targetType: 'device',
                targetId: $lockedDevice->public_id,
                metadata: [
                    'reason' => $reason,
                    'revoked_session_count' => $sessionIds->count(),
                ],
            ));

            return $lockedDevice;
        }, attempts: 3);
    }

    private function validateReason(string $reason): void
    {
        if (
            Str::length($reason) > 100
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $reason)
        ) {
            throw new InvalidArgumentException('The revocation reason must be a stable lowercase identifier.');
        }
    }
}
