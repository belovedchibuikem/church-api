<?php

namespace App\Http\Resources\Api\V1\User;

use App\Models\Testimony;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Testimony */
class TestimonyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
