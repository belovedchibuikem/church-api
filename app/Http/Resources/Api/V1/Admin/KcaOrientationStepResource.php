<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KcaOrientationStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'body' => $this->body,
            'display_type' => $this->display_type,
            'sequence' => $this->sequence,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
