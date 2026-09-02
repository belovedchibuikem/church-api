<?php

namespace App\Support\Kca;

use App\Models\KcaAdmissionLetter;
use App\Models\KcaApplication;
use App\Support\Identity\PersonDisplayName;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BuildKcaAdmissionLetterPreviewAction
{
    public function __construct(
        private readonly ResolveKcaApplicationChurchName $resolver,
        private readonly RenderKcaAdmissionLetterTemplateAction $renderTemplate,
    ) {}

    /** @return array<string, mixed> */
    public function handle(KcaApplication $application): array
    {
        if (! $this->resolver->canIssueFor($application)) {
            throw new NotFoundHttpException('Admission letter preview is not available for this application.');
        }

        $application->loadMissing(['person.profile', 'enrollment.cohort:id,name,public_id']);
        $governance = $this->resolver->governanceDefaults()
            ->loadMissing(['admissionLetterheadFile', 'admissionSignatureFile']);

        $draftLetter = (new KcaAdmissionLetter)->forceFill([
            'reference_code' => null,
            'signer_name' => $governance->admission_signer_name ?: $governance->certificate_signer_name,
            'signer_title' => $governance->admission_signer_title ?: $governance->certificate_signer_title,
            'batch_label' => $this->resolver->batchLabel($application),
            'issued_at' => now()->utc(),
        ]);
        $draftLetter->setRelation('application', $application);

        return [
            'id' => null,
            'application_id' => $application->public_id,
            'reference_code' => null,
            'applicant_name' => PersonDisplayName::of($application->person) ?: 'Applicant',
            'church_name' => $this->resolver->fromApplicationData($application->application_data),
            'batch_label' => $draftLetter->batch_label,
            'letter_body' => $this->renderTemplate->forApplication($application, $draftLetter, $governance),
            'signer_name' => $draftLetter->signer_name,
            'signer_title' => $draftLetter->signer_title,
            'letterhead_file_asset_id' => $governance->admissionLetterheadFile?->public_id,
            'signature_file_asset_id' => $governance->admissionSignatureFile?->public_id,
            'issued_at' => null,
            'status' => 'draft',
            'acceptance_status' => 'pending',
            'requires_guardian_confirmation' => filled(data_get($application->application_data, 'guardian_name')),
        ];
    }
}
