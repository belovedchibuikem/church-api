<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\AccessDecision;
use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class AuditRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return match (true) {
            $this->resource instanceof AuditEvent => [
                'id' => $this->public_id,
                'actor_user_id' => $this->actor?->public_id,
                'action' => $this->action,
                'target_type' => $this->target_type,
                'target_id' => $this->target_id,
                'scope_type' => $this->scope_type,
                'scope_id' => $this->scope_id,
                'correlation_id' => $this->correlation_id,
                'occurred_at' => $this->occurred_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof AccessDecision => [
                'id' => $this->public_id,
                'actor_user_id' => $this->actor?->public_id,
                'permission_code' => $this->permission_code,
                'scope_type' => $this->scope_type,
                'scope_id' => $this->scope_key,
                'allowed' => $this->allowed,
                'reason_code' => $this->reason_code->value,
                'correlation_id' => $this->correlation_id,
                'decided_at' => $this->decided_at?->utc()->toIso8601String(),
            ],
            default => throw new LogicException('Unsupported audit record.'),
        };
    }
}
