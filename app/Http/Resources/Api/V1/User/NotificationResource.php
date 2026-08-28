<?php

namespace App\Http\Resources\Api\V1\User;

use App\Models\CommunicationNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommunicationNotification */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $template = $this->recipient?->broadcast?->template;

        return [
            'id' => $this->public_id,
            'title' => $template?->subject,
            'body' => $template?->body,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
