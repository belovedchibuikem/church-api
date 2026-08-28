<?php

namespace App\Support\Platform;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class SetFeatureFlagStateAction
{
    public function __construct(
        private FeatureFlagResolver $resolver,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(FeatureFlag $flag, bool $enabled, User $actor): FeatureFlag
    {
        $updatedFlag = DB::transaction(function () use ($flag, $enabled, $actor): FeatureFlag {
            $lockedFlag = FeatureFlag::query()->lockForUpdate()->findOrFail($flag->getKey());

            if ($lockedFlag->is_enabled === $enabled) {
                return $lockedFlag;
            }

            $lockedFlag->is_enabled = $enabled;
            $lockedFlag->updatedBy()->associate($actor);
            $lockedFlag->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: $enabled
                    ? 'platform.feature_flag.enabled'
                    : 'platform.feature_flag.disabled',
                actor: $actor,
                targetType: 'feature_flag',
                targetId: $lockedFlag->public_id,
                scopeType: $lockedFlag->scope_type,
                scopeId: $lockedFlag->scope_key,
                metadata: [
                    'key' => $lockedFlag->key,
                    'environment' => $lockedFlag->environment,
                ],
            ));

            return $lockedFlag;
        }, attempts: 3);

        if ($updatedFlag->wasChanged('is_enabled')) {
            $this->resolver->invalidate();
        }

        return $updatedFlag;
    }
}
