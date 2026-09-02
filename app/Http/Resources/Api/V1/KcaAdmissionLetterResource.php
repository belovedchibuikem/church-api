<?php

namespace App\Http\Resources\Api\V1;

use App\Models\KcaAdmissionLetter;
use App\Support\Identity\PersonDisplayName;
use App\Support\Kca\ResolveKcaApplicationChurchName;
use App\Support\Kca\SyncKcaAdmissionLetterReference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KcaAdmissionLetterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var KcaAdmissionLetter $letter */
        $letter = $this->resource;
        $application = $letter->application;
        $resolver = app(ResolveKcaApplicationChurchName::class);
        $applicationData = is_array($application?->application_data) ? $application->application_data : [];

        return [
            'id' => $letter->public_id,
            'application_id' => $application?->public_id,
            'reference_code' => $letter->reference_code,
            'applicant_name' => PersonDisplayName::of($application?->person) ?: 'Applicant',
            'church_name' => $resolver->fromApplicationData($applicationData),
            'batch_label' => $letter->batch_label,
            'letter_body' => SyncKcaAdmissionLetterReference::inBody(
                (string) ($letter->letter_body ?? ''),
                (string) ($letter->reference_code ?? ''),
            ),
            'signer_name' => $letter->signer_name,
            'signer_title' => $letter->signer_title,
            'letterhead_file_asset_id' => $letter->letterheadFile?->public_id,
            'signature_file_asset_id' => $letter->signatureFile?->public_id,
            'issued_at' => $letter->issued_at?->utc()->toIso8601String(),
            'status' => 'issued',
            'acceptance_status' => $letter->applicant_accepted_at !== null ? 'accepted' : 'pending',
            'applicant_accepted_at' => $letter->applicant_accepted_at?->utc()->toIso8601String(),
            'applicant_signature_name' => $letter->applicant_signature_name,
            'applicant_signature_file_asset_id' => $letter->applicantSignatureFile?->public_id,
            'guardian_name' => $letter->guardian_name,
            'guardian_phone' => $letter->guardian_phone,
            'guardian_signature_name' => $letter->guardian_signature_name,
            'guardian_confirmed_at' => $letter->guardian_confirmed_at?->utc()->toIso8601String(),
            'requires_guardian_confirmation' => filled(data_get($applicationData, 'guardian_name')),
        ];
    }
}
