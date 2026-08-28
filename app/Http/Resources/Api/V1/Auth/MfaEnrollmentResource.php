<?php

namespace App\Http\Resources\Api\V1\Auth;

use App\Support\Security\TotpEnrollmentResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TotpEnrollmentResult */
class MfaEnrollmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'method_id' => $this->method->public_id,
            'method_type' => 'totp',
            'secret' => $this->secret,
            'provisioning_uri' => $this->provisioningUri,
            'recovery_codes' => $this->recoveryCodes,
        ];
    }
}
