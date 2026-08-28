<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScopeAssignmentResource extends JsonResource
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
            'scope' => [
                'type' => $this->scope_type,
                'id' => $this->scope_key,
            ],
            'assigned_at' => $this->created_at?->utc()->toIso8601String(),
            'role_assignment' => $this->whenLoaded('roleAssignment', fn (): array => [
                'id' => $this->roleAssignment->public_id,
                'role' => [
                    'id' => $this->roleAssignment->role->public_id,
                    'code' => $this->roleAssignment->role->code,
                    'name' => $this->roleAssignment->role->name,
                ],
                'user' => [
                    'id' => $this->roleAssignment->user->public_id,
                    'name' => $this->roleAssignment->user->name,
                    'email' => $this->roleAssignment->user->email,
                ],
                'assigned_at' => $this->roleAssignment->assigned_at->utc()->toIso8601String(),
                'expires_at' => $this->roleAssignment->expires_at?->utc()->toIso8601String(),
            ]),
        ];
    }
}
