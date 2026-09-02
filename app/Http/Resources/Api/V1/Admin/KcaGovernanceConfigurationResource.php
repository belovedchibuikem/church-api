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
            'admission_signer_name' => $this->admission_signer_name,
            'admission_signer_title' => $this->admission_signer_title,
            'admission_reference_prefix' => $this->admission_reference_prefix,
            'admission_letter_body_template' => $this->admission_letter_body_template,
            'admission_programme_commencement' => $this->admission_programme_commencement,
            'admission_programme_completion' => $this->admission_programme_completion,
            'admission_programme_venue' => $this->admission_programme_venue,
            'admission_programme_schedule' => $this->admission_programme_schedule,
            'admission_programme_mentor' => $this->admission_programme_mentor,
            'orientation_welcome' => $this->orientation_welcome,
            'orientation_review_welcome' => $this->orientation_review_welcome,
            'admission_letterhead_file_asset_id' => $this->admissionLetterheadFile?->public_id,
            'admission_signature_file_asset_id' => $this->admissionSignatureFile?->public_id,
            'configuration_revision' => $this->configuration_revision,
        ];
    }
}
