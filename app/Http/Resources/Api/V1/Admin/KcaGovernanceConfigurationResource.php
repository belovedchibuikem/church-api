<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KcaGovernanceConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'configured' => true,
            'pass_threshold_percent' => $this->pass_threshold_percent,
            'attendance_threshold_percent' => $this->attendance_threshold_percent,
            'require_final_assessment' => $this->require_final_assessment,
            'require_signed_pdf' => $this->require_signed_pdf,
            'certificate_signer_name' => $this->certificate_signer_name,
            'certificate_signer_title' => $this->certificate_signer_title,
            'configuration_revision' => $this->configuration_revision,
        ];
    }
}
