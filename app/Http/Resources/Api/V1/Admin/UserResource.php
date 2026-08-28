<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Identity\UserAccountStatus;
use App\Models\RoleAssignment;
use App\Models\ScopeAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'person_id' => $this->whenLoaded('person', fn (): ?string => $this->person?->public_id),
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->utc()->toIso8601String(),
            'account_status' => $this->account_status instanceof UserAccountStatus
                ? $this->account_status->value
                : $this->account_status,
            'suspension_reason' => $this->suspension_reason,
            'suspended_at' => $this->suspended_at?->utc()->toIso8601String(),
            'reactivated_at' => $this->reactivated_at?->utc()->toIso8601String(),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
            'roles' => $this->whenLoaded('roleAssignments', fn (): array => $this->roleAssignments
                ->map(fn (RoleAssignment $assignment): array => [
                    'assignment_id' => $assignment->public_id,
                    'code' => $assignment->role->code,
                    'name' => $assignment->role->name,
                    'expires_at' => $assignment->expires_at?->utc()->toIso8601String(),
                    'scopes' => $assignment->scopeAssignments
                        ->map(fn (ScopeAssignment $scope): array => [
                            'id' => $scope->public_id,
                            'type' => $scope->scope_type,
                            'scope_id' => $scope->scope_key,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all()),
        ];
    }
}
