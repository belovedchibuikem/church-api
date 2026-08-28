<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdministrativeLevelResource extends JsonResource
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
            'country_id' => $this->whenLoaded('country', fn (): string => $this->country->public_id),
            'code' => $this->code,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
        ];
    }
}
