<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ConfigureKcaGovernanceRequest;
use App\Http\Requests\Api\V1\Admin\KcaGovernanceActionRequest;
use App\Http\Resources\Api\V1\Admin\KcaGovernanceConfigurationResource;
use App\Models\KcaGovernanceConfiguration;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Kca\ConfigureKcaGovernanceAction;
use App\Support\Kca\KcaAdmissionLetterDefaultTemplate;
use Illuminate\Http\JsonResponse;

class KcaGovernanceController extends Controller
{
    public function show(KcaGovernanceActionRequest $request, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $configuration = KcaGovernanceConfiguration::query()->first();

        if ($configuration === null) {
            return ApiResponse::success($request, [
                'configured' => false,
                'pass_threshold_percent' => 70,
                'attendance_threshold_percent' => 75,
                'require_final_assessment' => true,
                'require_signed_pdf' => false,
                'certificate_signer_name' => null,
                'certificate_signer_title' => null,
                'admission_signer_name' => null,
                'admission_signer_title' => null,
                'admission_reference_prefix' => 'KCA/ADM',
                'admission_letter_body_template' => null,
                'admission_programme_commencement' => null,
                'admission_programme_completion' => null,
                'admission_programme_venue' => null,
                'admission_programme_schedule' => null,
                'admission_programme_mentor' => null,
                'orientation_welcome' => null,
                'orientation_review_welcome' => null,
                'admission_letterhead_file_asset_id' => null,
                'admission_signature_file_asset_id' => null,
                ...$this->admissionLetterTemplateDefaults(),
            ]);
        }

        $configuration->load(['admissionLetterheadFile:id,public_id', 'admissionSignatureFile:id,public_id']);

        return ApiResponse::success($request, [
            ...(new KcaGovernanceConfigurationResource($configuration))->resolve($request),
            ...$this->admissionLetterTemplateDefaults(),
        ]);
    }

    /** @return array<string, string> */
    private function admissionLetterTemplateDefaults(): array
    {
        return [
            'admission_letter_template_default' => KcaAdmissionLetterDefaultTemplate::body(),
            'admission_letter_template_placeholders' => KcaAdmissionLetterDefaultTemplate::PLACEHOLDER_HELP,
        ];
    }

    public function configure(
        ConfigureKcaGovernanceRequest $request,
        ConfigureKcaGovernanceAction $configure,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $configuration = $configure->handle($request->validated(), $context->actor($request));

        return ApiResponse::success($request, (new KcaGovernanceConfigurationResource($configuration))->resolve($request));
    }
}
