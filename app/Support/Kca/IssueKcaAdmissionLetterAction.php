<?php

namespace App\Support\Kca;

use App\Models\FileAsset;
use App\Models\KcaAdmissionLetter;
use App\Models\KcaApplication;
use App\Models\KcaGovernanceConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IssueKcaAdmissionLetterAction
{
    public function __construct(
        private readonly ResolveKcaApplicationChurchName $resolver,
        private readonly RenderKcaAdmissionLetterTemplateAction $renderTemplate,
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        KcaApplication $application,
        User $actor,
        ?string $batchLabel = null,
        ?string $letterBody = null,
        ?string $signerName = null,
        ?string $signerTitle = null,
        ?FileAsset $letterheadFile = null,
        ?FileAsset $signatureFile = null,
    ): KcaAdmissionLetter {
        if (! $this->resolver->canIssueFor($application)) {
            throw new InvalidArgumentException('Admission letters can only be issued for accepted applications.');
        }

        return DB::transaction(function () use (
            $application,
            $actor,
            $batchLabel,
            $letterBody,
            $signerName,
            $signerTitle,
            $letterheadFile,
            $signatureFile,
        ): KcaAdmissionLetter {
            $lockedApplication = KcaApplication::query()
                ->with(['person.profile', 'enrollment.cohort:id,name,public_id'])
                ->lockForUpdate()
                ->findOrFail($application->getKey());

            $existing = KcaAdmissionLetter::query()
                ->where('kca_application_id', $lockedApplication->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing->load([
                    'application.person.profile',
                    'letterheadFile:id,public_id',
                    'signatureFile:id,public_id',
                ]);
            }

            $governance = $this->resolver->governanceDefaults()
                ->loadMissing(['admissionLetterheadFile', 'admissionSignatureFile']);
            $resolvedBatch = $batchLabel ?: $this->resolver->batchLabel($lockedApplication);

            $draftLetter = (new KcaAdmissionLetter)->forceFill([
                'reference_code' => 'Pending',
                'batch_label' => $resolvedBatch,
                'signer_name' => $signerName ?: $governance->admission_signer_name ?: $governance->certificate_signer_name,
                'signer_title' => $signerTitle ?: $governance->admission_signer_title ?: $governance->certificate_signer_title,
                'issued_at' => now()->utc(),
            ]);
            $draftLetter->setRelation('application', $lockedApplication);

            $letter = (new KcaAdmissionLetter)->forceFill([
                'kca_application_id' => $lockedApplication->getKey(),
                'reference_code' => $this->nextReferenceCode($governance),
                'batch_label' => $resolvedBatch,
                'letter_body' => $letterBody ?: $this->renderTemplate->forApplication($lockedApplication, $draftLetter, $governance),
                'signer_name' => $draftLetter->signer_name,
                'signer_title' => $draftLetter->signer_title,
                'letterhead_file_asset_id' => ($letterheadFile ?? $governance->admissionLetterheadFile)?->getKey(),
                'signature_file_asset_id' => ($signatureFile ?? $governance->admissionSignatureFile)?->getKey(),
                'issued_by_user_id' => $actor->getKey(),
                'issued_at' => now()->utc(),
            ]);
            $letter->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.admission_letter.issued',
                actor: $actor,
                targetType: 'kca_admission_letter',
                targetId: $letter->public_id,
                metadata: [
                    'application_id' => $lockedApplication->public_id,
                    'reference_code' => $letter->reference_code,
                    'applicant_name' => PersonDisplayName::of($lockedApplication->person) ?: 'Applicant',
                ],
            ));

            return $letter->load([
                'application.person.profile',
                'letterheadFile:id,public_id',
                'signatureFile:id,public_id',
            ]);
        }, attempts: 3);
    }

    private function nextReferenceCode(KcaGovernanceConfiguration $governance): string
    {
        $year = now()->year;
        $count = KcaAdmissionLetter::query()->whereYear('issued_at', $year)->count() + 1;
        $prefix = trim((string) ($governance->admission_reference_prefix ?? 'KCA/ADM'));
        $prefix = $prefix !== '' ? $prefix : 'KCA/ADM';

        return sprintf('%s/%d/%04d', rtrim($prefix, '/'), $year, $count);
    }
}
