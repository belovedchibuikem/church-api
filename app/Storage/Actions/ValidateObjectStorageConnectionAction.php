<?php

namespace App\Storage\Actions;

use App\Models\ObjectStorageConfiguration;
use App\Storage\Contracts\ObjectStorageConnectionValidator;
use App\Storage\Data\ObjectStorageValidationResult;

class ValidateObjectStorageConnectionAction
{
    public function __construct(
        private ObjectStorageConnectionValidator $validator,
    ) {}

    public function handle(ObjectStorageConfiguration $configuration): ObjectStorageValidationResult
    {
        $configuration->refresh();
        $revision = $configuration->configuration_revision;
        $result = $this->validator->validate($configuration);
        $attemptedAt = now();

        $updated = ObjectStorageConfiguration::query()
            ->whereKey($configuration->getKey())
            ->where('configuration_revision', $revision)
            ->update([
                'last_validation_status' => $result->status->value,
                'last_validation_failure_code' => $result->failureCode,
                'last_validation_attempted_at' => $attemptedAt,
                'validated_at' => $result->isSuccessful() ? $attemptedAt : null,
                'is_active' => $result->isSuccessful() && $configuration->is_active,
                'activated_at' => $result->isSuccessful() ? $configuration->activated_at : null,
            ]);

        if ($updated === 0) {
            return ObjectStorageValidationResult::failed('configuration_changed');
        }

        $configuration->refresh();

        return $result;
    }
}
