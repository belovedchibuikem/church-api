<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
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
            'iso_code' => $this->iso_code,
            'name' => $this->name,
            'local_name' => $this->local_name,
            'calling_code' => $this->calling_code,
            'currency_code' => $this->currency_code,
            'default_timezone' => $this->default_timezone,
            'locale' => $this->locale,
            'created_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }
}
