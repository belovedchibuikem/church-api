<?php

namespace App\Http\Resources\Api\V1\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KcaCertificateVerificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $revoked = $this->revocation !== null;

        return [
            'verified' => ! $revoked,
            'certificate_number' => $this->certificate_number,
            'completion_on' => $this->completion_on->toDateString(),
            'issued_at' => $this->issued_at->utc()->toIso8601String(),
            'revoked' => $revoked,
            'revoked_at' => $this->revocation?->revoked_at?->utc()->toIso8601String(),
        ];
    }
}
