<?php

namespace App\Support\Audit;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordAuditEventAction
{
    public function handle(AuditEventData $data): AuditEvent
    {
        $this->validateData($data);

        $correlationId = Context::get('correlation_id');

        return AuditEvent::query()->create([
            'actor_user_id' => $data->actor?->getKey(),
            'action' => $data->action,
            'target_type' => $data->targetType,
            'target_id' => $data->targetId,
            'scope_type' => $data->scopeType,
            'scope_id' => $data->scopeId,
            'correlation_id' => is_string($correlationId) && Str::isUuid($correlationId)
                ? $correlationId
                : null,
            'metadata' => $data->metadata,
            'occurred_at' => now()->utc(),
        ]);
    }

    private function validateData(AuditEventData $data): void
    {
        if (
            Str::length($data->action) > 191
            || ! Str::isMatch('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $data->action)
        ) {
            throw new InvalidArgumentException('The audit action must be a stable lowercase identifier.');
        }

        $this->validatePair($data->targetType, $data->targetId, 'target');
        $this->validatePair($data->scopeType, $data->scopeId, 'scope');
    }

    private function validatePair(?string $type, ?string $identifier, string $name): void
    {
        if (($type === null) !== ($identifier === null)) {
            throw new InvalidArgumentException("The audit {$name} type and identifier must be provided together.");
        }

        if ($type !== null && Str::length($type) > 100) {
            throw new InvalidArgumentException("The audit {$name} type is too long.");
        }

        if ($identifier !== null && Str::length($identifier) > 64) {
            throw new InvalidArgumentException("The audit {$name} identifier is too long.");
        }
    }
}
