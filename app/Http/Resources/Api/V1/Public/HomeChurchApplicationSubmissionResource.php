<?php

namespace App\Http\Resources\Api\V1\Public;

use App\Models\HomeChurchApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HomeChurchApplication */
class HomeChurchApplicationSubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->public_id,
            'status' => $this->status->value,
            'received_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
