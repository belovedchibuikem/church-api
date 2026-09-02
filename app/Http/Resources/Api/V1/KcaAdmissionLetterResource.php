<?php

namespace App\Http\Resources\Api\V1;

use App\Models\KcaAdmissionLetter;
use App\Support\Identity\PersonDisplayName;
use App\Support\Kca\ResolveKcaApplicationChurchName;
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

        return [
            'id' => $letter->public_id,
            'application_id' => $application?->public_id,
            'reference_code' => $letter->reference_code,
            'applicant_name' => PersonDisplayName::of($application?->person) ?: 'Applicant',
            'church_name' => $resolver->fromApplicationData($application?->application_data),
            'batch_label' => $letter->batch_label,
            'letter_body' => $letter->letter_body,
            'signer_name' => $letter->signer_name,
            'signer_title' => $letter->signer_title,
            'letterhead_file_asset_id' => $letter->letterheadFile?->public_id,
            'signature_file_asset_id' => $letter->signatureFile?->public_id,
            'issued_at' => $letter->issued_at?->utc()->toIso8601String(),
            'status' => 'issued',
        ];
    }
}
