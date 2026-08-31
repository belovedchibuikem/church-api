<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminWorkItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'due_at' => $this->due_at?->utc()->toIso8601String(),
            'closed_at' => $this->closed_at?->utc()->toIso8601String(),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
            'assigned_to' => $this->whenLoaded('assignee', fn (): ?array => $this->assignee === null ? null : [
                'id' => $this->assignee->public_id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ]),
            'created_by' => $this->whenLoaded('creator', fn (): ?array => $this->creator === null ? null : [
                'id' => $this->creator->public_id,
                'name' => $this->creator->name,
            ]),
        ];
    }
}
