<?php

namespace App\Support\Kca;

use App\Exceptions\KcaCertificationNotEligibleException;
use App\Exceptions\KcaIdempotencyConflictException;
use App\Models\KcaCertificate;
use App\Models\KcaEnrollment;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Kca\Contracts\KcaCertificationEligibilityPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class IssueKcaCertificateAction
{
    public function __construct(
        private KcaCertificationEligibilityPolicy $eligibilityPolicy,
        private RecordAuditEventAction $recordAuditEvent,
        private KcaCertificateCodeHasher $codeHasher,
    ) {}

    public function handle(
        KcaEnrollment $enrollment,
        string $certificateNumber,
        CarbonInterface $completionOn,
        string $verificationCode,
        string $idempotencyKey,
        User $actor,
    ): KcaCertificate {
        $this->validateInput($certificateNumber, $verificationCode, $idempotencyKey);
        $verificationCodeHash = $this->codeHasher->hash($verificationCode);
        $issuanceKeyHash = hash_hmac('sha256', $idempotencyKey, $this->hashKey());
        $completionOn = $completionOn->toImmutable()->startOfDay();

        return DB::transaction(function () use (
            $enrollment,
            $certificateNumber,
            $completionOn,
            $verificationCodeHash,
            $issuanceKeyHash,
            $actor,
        ): KcaCertificate {
            $lockedEnrollment = KcaEnrollment::query()->lockForUpdate()->findOrFail($enrollment->getKey());
            $existing = KcaCertificate::query()
                ->where(function (Builder $query) use ($lockedEnrollment, $issuanceKeyHash): void {
                    $query->where('kca_enrollment_id', $lockedEnrollment->getKey())
                        ->orWhere('issuance_key_hash', $issuanceKeyHash);
                })
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (
                    $existing->kca_enrollment_id !== $lockedEnrollment->getKey()
                    || $existing->certificate_number !== $certificateNumber
                    || ! hash_equals($existing->verification_code_hash, $verificationCodeHash)
                    || ! hash_equals($existing->issuance_key_hash, $issuanceKeyHash)
                    || ! $existing->completion_on->isSameDay($completionOn)
                ) {
                    throw new KcaIdempotencyConflictException;
                }

                return $existing;
            }

            $decision = $this->eligibilityPolicy->decide($lockedEnrollment);

            if (! $decision->eligible) {
                throw new KcaCertificationNotEligibleException($decision);
            }

            $certificate = (new KcaCertificate)->forceFill([
                'kca_enrollment_id' => $lockedEnrollment->getKey(),
                'person_id' => $lockedEnrollment->person_id,
                'certificate_number' => $certificateNumber,
                'completion_on' => $completionOn,
                'issued_at' => now()->utc(),
                'digital_signature_reference' => null,
                'verification_code_hash' => $verificationCodeHash,
                'issuance_key_hash' => $issuanceKeyHash,
                'issued_by_user_id' => $actor->getKey(),
            ]);
            $certificate->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.certificate.issued',
                actor: $actor,
                targetType: 'kca_certificate',
                targetId: $certificate->public_id,
                scopeType: 'kca_cohort',
                scopeId: $lockedEnrollment->cohort()->value('public_id'),
                metadata: [
                    'enrollment_id' => $lockedEnrollment->public_id,
                    'certificate_number' => $certificateNumber,
                    'completion_on' => $completionOn->toDateString(),
                    'eligibility_reason' => $decision->reasonCode,
                ],
            ));

            return $certificate;
        }, attempts: 3);
    }

    private function validateInput(
        string $certificateNumber,
        string $verificationCode,
        string $idempotencyKey,
    ): void {
        if (trim($certificateNumber) === '' || Str::length($certificateNumber) > 100) {
            throw new InvalidArgumentException('Certificate numbers must contain 1 to 100 characters.');
        }

        if (! Str::isUlid($verificationCode) && ! Str::isUuid($verificationCode)) {
            throw new InvalidArgumentException('Certificate verification codes must be opaque ULIDs or UUIDs.');
        }

        if ($idempotencyKey === '' || Str::length($idempotencyKey) > 255) {
            throw new InvalidArgumentException('Certificate idempotency keys must contain 1 to 255 characters.');
        }
    }

    private function hashKey(): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new InvalidArgumentException('The application key is required for certificate protection.');
        }

        return $key;
    }
}
