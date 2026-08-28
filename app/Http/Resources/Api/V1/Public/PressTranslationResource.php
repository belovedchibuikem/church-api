<?php

namespace App\Http\Resources\Api\V1\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PressTranslationResource extends JsonResource
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
            'language' => $this->target_language_code,
            'title' => $this->translated_title,
            'subtitle' => $this->translated_subtitle,
            'description' => $this->translated_description,
            'approved_at' => $this->approved_at?->utc()->toIso8601String(),
        ];
    }
}
