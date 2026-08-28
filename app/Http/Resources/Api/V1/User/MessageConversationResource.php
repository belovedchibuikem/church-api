<?php

namespace App\Http\Resources\Api\V1\User;

use App\Models\MessageConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MessageConversation */
class MessageConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'subject' => $this->subject,
            'participant_ids' => $this->whenLoaded(
                'participants',
                fn () => $this->participants->pluck('public_id')->values()->all(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
