<?php

namespace App\Support\Kca;

use App\Models\KcaApplication;
use App\Support\Identity\PersonDisplayName;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BuildKcaAdmissionLetterPreviewAction
{
    public function __construct(
        private readonly ResolveKcaApplicationChurchName $resolver,
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

        $applicantName = PersonDisplayName::of($application->person) ?: 'Applicant';
        $churchName = $this->resolver->fromApplicationData($application->application_data);
        $batchLabel = $this->resolver->batchLabel($application);

        return [
            'id' => null,
            'application_id' => $application->public_id,
            'reference_code' => null,
            'applicant_name' => $applicantName,
            'church_name' => $churchName,
            'batch_label' => $batchLabel,
            'letter_body' => $this->resolver->defaultLetterBody($applicantName, $churchName, $batchLabel),
            'signer_name' => $governance->admission_signer_name ?: $governance->certificate_signer_name,
            'signer_title' => $governance->admission_signer_title ?: $governance->certificate_signer_title,
            'letterhead_file_asset_id' => $governance->admissionLetterheadFile?->public_id,
            'signature_file_asset_id' => $governance->admissionSignatureFile?->public_id,
            'issued_at' => null,
            'status' => 'draft',
        ];
    }
}
