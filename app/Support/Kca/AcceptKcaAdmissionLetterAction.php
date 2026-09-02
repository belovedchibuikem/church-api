<?php

namespace App\Support\Kca;

use App\Models\FileAsset;
use App\Models\KcaAdmissionLetter;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AcceptKcaAdmissionLetterAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly RenderKcaAdmissionLetterTemplateAction $renderTemplate,
    ) {}

    public function handle(
        KcaAdmissionLetter $letter,
        User $actor,
        string $applicantSignatureName,
        ?FileAsset $applicantSignatureFile = null,
        ?string $guardianName = null,
        ?string $guardianSignatureName = null,
        ?string $guardianPhone = null,
    ): KcaAdmissionLetter {
        $signature = trim($applicantSignatureName);
        if ($signature === '') {
            throw new InvalidArgumentException('Applicant signature is required.');
        }

        return DB::transaction(function () use (
            $letter,
            $actor,
            $signature,
            $applicantSignatureFile,
            $guardianName,
            $guardianSignatureName,
            $guardianPhone,
        ): KcaAdmissionLetter {
            $locked = KcaAdmissionLetter::query()
                ->with(['application.person.profile'])
                ->lockForUpdate()
                ->findOrFail($letter->getKey());

            if ($locked->applicant_accepted_at !== null) {
                return $locked->load([
                    'letterheadFile:id,public_id',
                    'signatureFile:id,public_id',
                    'applicantSignatureFile:id,public_id',
                ]);
            }

            $now = now()->utc();
            $locked->forceFill([
                'applicant_accepted_at' => $now,
                'applicant_signature_name' => $signature,
                'applicant_signature_file_asset_id' => $applicantSignatureFile?->getKey(),
                'guardian_name' => filled($guardianName) ? trim($guardianName) : null,
                'guardian_signature_name' => filled($guardianSignatureName) ? trim($guardianSignatureName) : null,
                'guardian_phone' => filled($guardianPhone) ? trim($guardianPhone) : null,
                'guardian_confirmed_at' => filled($guardianSignatureName) ? $now : null,
            ])->save();

            if ($locked->application !== null) {
                $locked->forceFill([
                    'letter_body' => $this->renderTemplate->forApplication($locked->application, $locked),
                ])->save();
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.admission_letter.accepted',
                actor: $actor,
                targetType: 'kca_admission_letter',
                targetId: $locked->public_id,
                metadata: [
                    'application_id' => $locked->application?->public_id,
                    'reference_code' => $locked->reference_code,
                ],
            ));

            return $locked->load([
                'application.person.profile',
                'letterheadFile:id,public_id',
                'signatureFile:id,public_id',
                'applicantSignatureFile:id,public_id',
            ]);
        }, attempts: 3);
    }
}
