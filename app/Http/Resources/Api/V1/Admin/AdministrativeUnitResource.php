<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdministrativeUnitResource extends JsonResource
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
            'name' => $this->name,
            'reference_code' => $this->reference_code,
            'country' => $this->whenLoaded('country', fn (): array => [
                'id' => $this->country->public_id,
                'iso_code' => $this->country->iso_code,
                'name' => $this->country->name,
            ]),
            'administrative_level' => $this->whenLoaded('administrativeLevel', fn (): array => [
                'id' => $this->administrativeLevel->public_id,
                'code' => $this->administrativeLevel->code,
                'name' => $this->administrativeLevel->name,
                'sort_order' => $this->administrativeLevel->sort_order,
            ]),
            'parent' => $this->whenLoaded('parent', fn (): ?array => $this->parent === null ? null : [
                'id' => $this->parent->public_id,
                'name' => $this->parent->name,
            ]),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }
}
