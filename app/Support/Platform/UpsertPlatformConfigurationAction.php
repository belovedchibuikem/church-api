<?php

namespace App\Support\Platform;

use App\Models\PlatformConfiguration;
use App\Models\User;
use App\Platform\ConfigurationClassification;
use App\Platform\ConfigurationValueType;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class UpsertPlatformConfigurationAction
{
    public function __construct(
        private PlatformConfigurationValueCodec $codec,
        private PlatformConfigurationResolver $resolver,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        PlatformKey $key,
        ConfigurationValueType $type,
        ConfigurationClassification $classification,
        mixed $value,
        PlatformContext $context,
        User $actor,
    ): PlatformConfiguration {
        $storedValue = $this->codec->encode($type, $classification, $value);

        $configuration = DB::transaction(function () use (
            $key,
            $type,
            $classification,
            $storedValue,
            $context,
            $actor,
        ): PlatformConfiguration {
            $configuration = PlatformConfiguration::query()
                ->where('key', $key->value)
                ->where('context_hash', $context->hash())
                ->lockForUpdate()
                ->first();
            $wasCreated = $configuration === null;
            $configuration ??= new PlatformConfiguration;
            $configuration->fill([
                'key' => $key->value,
                'value_type' => $type,
                'classification' => $classification,
                'environment' => $context->environment,
                'scope_type' => $context->scope?->type,
                'scope_key' => $context->scope?->key,
                'context_hash' => $context->hash(),
                'stored_value' => $storedValue,
            ]);
            $configuration->updatedBy()->associate($actor);
            $configuration->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: $wasCreated
                    ? 'platform.configuration.created'
                    : 'platform.configuration.updated',
                actor: $actor,
                targetType: 'platform_configuration',
                targetId: $configuration->public_id,
                scopeType: $context->scope?->type,
                scopeId: $context->scope?->key,
                metadata: [
                    'key' => $key->value,
                    'value_type' => $type->value,
                    'classification' => $classification->value,
                    'environment' => $context->environment,
                ],
            ));

            return $configuration;
        }, attempts: 3);

        $this->resolver->invalidate();

        return $configuration;
    }
}
