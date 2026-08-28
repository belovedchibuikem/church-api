<?php

namespace App\Support\Security;

use App\Exceptions\RevokedDeviceException;
use App\Exceptions\SuspendedAccountException;
use App\Models\Device;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RegisterDeviceAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(User $user, RegisterDeviceData $data, ?User $actor = null): Device
    {
        $this->validateData($data);

        $identifierHash = hash_hmac('sha256', $data->identifier, (string) config('app.key'));

        return DB::transaction(function () use ($user, $data, $actor, $identifierHash): Device {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->isSuspended()) {
                throw new SuspendedAccountException;
            }

            $existingDevice = Device::query()
                ->whereBelongsTo($lockedUser)
                ->where('identifier_hash', $identifierHash)
                ->lockForUpdate()
                ->first();

            if ($existingDevice !== null) {
                if ($existingDevice->revoked_at !== null) {
                    throw new RevokedDeviceException;
                }

                $existingDevice->fill($this->metadata($data));
                $metadataChanged = $existingDevice->isDirty([
                    'label',
                    'device_type',
                    'platform',
                    'app_version',
                ]);
                $existingDevice->last_seen_at = now()->utc();
                $existingDevice->save();

                if ($metadataChanged) {
                    $this->recordAuditEvent->handle(new AuditEventData(
                        action: 'security.device.metadata_updated',
                        actor: $actor,
                        targetType: 'device',
                        targetId: $existingDevice->public_id,
                        metadata: [
                            'device_type' => $existingDevice->device_type,
                            'platform' => $existingDevice->platform,
                        ],
                    ));
                }

                return $existingDevice;
            }

            $seenAt = now()->utc();
            $device = Device::query()->create([
                'user_id' => $lockedUser->getKey(),
                'identifier_hash' => $identifierHash,
                ...$this->metadata($data),
                'first_seen_at' => $seenAt,
                'last_seen_at' => $seenAt,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'security.device.registered',
                actor: $actor,
                targetType: 'device',
                targetId: $device->public_id,
                metadata: [
                    'device_type' => $device->device_type,
                    'platform' => $device->platform,
                ],
            ));

            return $device;
        }, attempts: 3);
    }

    /**
     * @return array{label: ?string, device_type: ?string, platform: ?string, app_version: ?string}
     */
    private function metadata(RegisterDeviceData $data): array
    {
        return [
            'label' => $data->label,
            'device_type' => $data->deviceType,
            'platform' => $data->platform,
            'app_version' => $data->appVersion,
        ];
    }

    private function validateData(RegisterDeviceData $data): void
    {
        if ($data->identifier === '' || Str::length($data->identifier) > 512) {
            throw new InvalidArgumentException('The device identifier must contain between 1 and 512 characters.');
        }

        $limits = [
            'label' => [$data->label, 100],
            'device type' => [$data->deviceType, 50],
            'platform' => [$data->platform, 100],
            'app version' => [$data->appVersion, 50],
        ];

        foreach ($limits as $name => [$value, $maximum]) {
            if ($value !== null && Str::length($value) > $maximum) {
                throw new InvalidArgumentException("The device {$name} is too long.");
            }
        }
    }
}
