<?php

namespace App\Storage\Actions;

use App\Exceptions\ObjectStorageConnectionValidationException;
use App\Models\ObjectStorageConfiguration;
use App\Models\User;
use App\Storage\Contracts\ObjectStorageConnectionValidator;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class ActivateObjectStorageAction
{
    public function __construct(
        private ObjectStorageConnectionValidator $validator,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(ObjectStorageConfiguration $configuration, ?User $actor = null): ObjectStorageConfiguration
    {
        $configuration->refresh();
        $revision = $configuration->configuration_revision;
        $result = $this->validator->validate($configuration);
        $attemptedAt = now();

        $activatedConfiguration = DB::transaction(function () use (
            $configuration,
            $revision,
            $result,
            $attemptedAt,
            $actor,
        ): ObjectStorageConfiguration {
            $lockedConfiguration = ObjectStorageConfiguration::query()
                ->lockForUpdate()
                ->findOrFail($configuration->getKey());

            if ($lockedConfiguration->configuration_revision !== $revision) {
                throw new ObjectStorageConnectionValidationException('configuration_changed');
            }

            $lockedConfiguration->forceFill([
                'last_validation_status' => $result->status,
                'last_validation_failure_code' => $result->failureCode,
                'last_validation_attempted_at' => $attemptedAt,
                'validated_at' => $result->isSuccessful() ? $attemptedAt : null,
                'is_active' => $result->isSuccessful(),
                'activated_at' => $result->isSuccessful() ? $attemptedAt : null,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: $result->isSuccessful()
                    ? 'platform.object_storage.activated'
                    : 'platform.object_storage.activation_failed',
                actor: $actor,
                targetType: 'object_storage_configuration',
                targetId: 's3',
                scopeType: 'global',
                scopeId: 'platform',
                metadata: [
                    'configuration_revision' => $lockedConfiguration->configuration_revision,
                    'failure_code' => $result->failureCode,
                ],
            ));

            return $lockedConfiguration;
        }, attempts: 3);

        if (! $result->isSuccessful()) {
            throw new ObjectStorageConnectionValidationException(
                $result->failureCode ?? 'connection_failed',
            );
        }

        return $activatedConfiguration;
    }
}
