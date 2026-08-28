<?php

namespace App\Storage\Actions;

use App\Models\ObjectStorageConfiguration;
use App\Models\User;
use App\Storage\Contracts\ObjectStorageConnectionValidator;
use App\Storage\Data\ObjectStorageValidationResult;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class ValidateObjectStorageConnectionAction
{
    public function __construct(
        private ObjectStorageConnectionValidator $validator,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        ObjectStorageConfiguration $configuration,
        ?User $actor = null,
    ): ObjectStorageValidationResult {
        $configuration->refresh();
        $revision = $configuration->configuration_revision;
        $result = $this->validator->validate($configuration);
        $attemptedAt = now();

        return DB::transaction(function () use (
            $configuration,
            $revision,
            $result,
            $attemptedAt,
            $actor,
        ): ObjectStorageValidationResult {
            $lockedConfiguration = ObjectStorageConfiguration::query()
                ->lockForUpdate()
                ->findOrFail($configuration->getKey());

            if ($lockedConfiguration->configuration_revision !== $revision) {
                return ObjectStorageValidationResult::failed('configuration_changed');
            }

            $lockedConfiguration->forceFill([
                'last_validation_status' => $result->status->value,
                'last_validation_failure_code' => $result->failureCode,
                'last_validation_attempted_at' => $attemptedAt,
                'validated_at' => $result->isSuccessful() ? $attemptedAt : null,
                'is_active' => $result->isSuccessful() && $configuration->is_active,
                'activated_at' => $result->isSuccessful() ? $configuration->activated_at : null,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: $result->isSuccessful()
                    ? 'platform.object_storage.validation_succeeded'
                    : 'platform.object_storage.validation_failed',
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

            $configuration->setRawAttributes($lockedConfiguration->getAttributes(), true);

            return $result;
        }, attempts: 3);
    }
}
