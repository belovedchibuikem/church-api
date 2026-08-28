<?php

namespace App\Support\Platform;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpsertFeatureFlagAction
{
    public function __construct(
        private FeatureFlagResolver $resolver,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        PlatformKey $key,
        PlatformContext $context,
        int $rolloutPercentage,
        User $actor,
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $endsAt = null,
    ): FeatureFlag {
        if ($rolloutPercentage < 0 || $rolloutPercentage > 100) {
            throw new InvalidArgumentException('Feature rollout percentages must be between 0 and 100.');
        }

        $startsAt = $startsAt?->toImmutable()->utc();
        $endsAt = $endsAt?->toImmutable()->utc();

        if ($startsAt !== null && $endsAt !== null && ! $endsAt->isAfter($startsAt)) {
            throw new InvalidArgumentException('Feature activation must end after it starts.');
        }

        $flag = DB::transaction(function () use (
            $key,
            $context,
            $rolloutPercentage,
            $actor,
            $startsAt,
            $endsAt,
        ): FeatureFlag {
            $flag = FeatureFlag::query()
                ->where('key', $key->value)
                ->where('context_hash', $context->hash())
                ->lockForUpdate()
                ->first();
            $wasCreated = $flag === null;
            $flag ??= new FeatureFlag;
            $flag->fill([
                'key' => $key->value,
                'environment' => $context->environment,
                'scope_type' => $context->scope?->type,
                'scope_key' => $context->scope?->key,
                'context_hash' => $context->hash(),
                'rollout_percentage' => $rolloutPercentage,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
            $flag->updatedBy()->associate($actor);
            $flag->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: $wasCreated
                    ? 'platform.feature_flag.created'
                    : 'platform.feature_flag.updated',
                actor: $actor,
                targetType: 'feature_flag',
                targetId: $flag->public_id,
                scopeType: $context->scope?->type,
                scopeId: $context->scope?->key,
                metadata: [
                    'key' => $key->value,
                    'environment' => $context->environment,
                    'rollout_percentage' => $rolloutPercentage,
                    'starts_at' => $startsAt?->toIso8601String(),
                    'ends_at' => $endsAt?->toIso8601String(),
                ],
            ));

            return $flag;
        }, attempts: 3);

        $this->resolver->invalidate();

        return $flag;
    }
}
