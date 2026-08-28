<?php

namespace App\Http\Resources\Api\V1\User;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'conversation_id' => $this->relationLoaded('conversation')
                ? $this->conversation?->public_id
                : null,
            'sender_person_id' => $this->relationLoaded('sender')
                ? $this->sender?->public_id
                : null,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
