<?php

namespace Tests\Feature\Support\Kca;

use App\Exceptions\KcaCertificateImmutableException;
use App\Exceptions\KcaCertificationNotEligibleException;
use App\Models\AuditEvent;
use App\Models\KcaCertificate;
use App\Models\KcaEnrollment;
use App\Models\User;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Kca\Contracts\KcaCertificationEligibilityPolicy;
use App\Support\Kca\IssueKcaCertificateAction;
use App\Support\Kca\KcaCertificationEligibilityDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class IssueKcaCertificateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_policy_denies_certification_with_structured_pending_requirements(): void
    {
        $enrollment = KcaEnrollment::factory()->create();
        $actor = User::factory()->create();

        try {
            $this->app->make(IssueKcaCertificateAction::class)->handle(
                $enrollment,
                'KCA-CERT-0001',
                now(),
                Str::ulid()->toString(),
                'certificate-retry-1',
                $actor,
            );
            $this->fail('Expected certification eligibility denial.');
        } catch (KcaCertificationNotEligibleException $exception) {
            $this->assertFalse($exception->decision->eligible);
            $this->assertSame('assignments_incomplete', $exception->decision->reasonCode);
            $this->assertSame(['final_assessment'], $exception->decision->unmetRequirements);
            $this->assertSame(0, KcaCertificate::query()->count());
            $this->assertSame(0, AuditEvent::query()->count());
        }
    }

    public function test_approved_certificate_retry_is_idempotent_private_and_audited_once(): void
    {
        $this->approveCertification();
        $enrollment = KcaEnrollment::factory()->create();
        $actor = User::factory()->create();
        $verificationCode = Str::ulid()->toString();
        $action = $this->app->make(IssueKcaCertificateAction::class);

        $first = $action->handle(
            $enrollment,
            'KCA-CERT-0002',
            now(),
            $verificationCode,
            'certificate-retry-2',
            $actor,
        );
        $second = $action->handle(
            $enrollment,
            'KCA-CERT-0002',
            now(),
            $verificationCode,
            'certificate-retry-2',
            $actor,
        );

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame($enrollment->person_id, $first->person_id);
        $this->assertNull($first->digital_signature_reference);
        $this->assertNotSame($verificationCode, $first->getRawOriginal('verification_code_hash'));
        $this->assertArrayNotHasKey('verification_code_hash', $first->toArray());
        $this->assertArrayNotHasKey('issuance_key_hash', $first->toArray());
        $this->assertSame(1, KcaCertificate::query()->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'kca.certificate.issued')->count());
    }

    public function test_issued_certificate_rejects_update_and_delete(): void
    {
        $certificate = KcaCertificate::factory()->create();

        try {
            $certificate->certificate_number = 'KCA-CERT-CORRECTED';
            $certificate->save();
            $this->fail('Expected certificate update denial.');
        } catch (KcaCertificateImmutableException) {
            $this->assertNotSame('KCA-CERT-CORRECTED', $certificate->refresh()->certificate_number);
        }

        $this->expectException(KcaCertificateImmutableException::class);

        $certificate->delete();
    }

    public function test_audit_failure_rolls_back_certificate_issuance(): void
    {
        $this->approveCertification();
        $enrollment = KcaEnrollment::factory()->create();
        $actor = User::factory()->create();
        $this->mock(RecordAuditEventAction::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('audit unavailable'));

        try {
            $this->app->make(IssueKcaCertificateAction::class)->handle(
                $enrollment,
                'KCA-CERT-0003',
                now(),
                Str::ulid()->toString(),
                'certificate-retry-3',
                $actor,
            );
            $this->fail('Expected the audit exception.');
        } catch (RuntimeException) {
            $this->assertSame(0, KcaCertificate::query()->count());
        }
    }

    private function approveCertification(): void
    {
        $this->app->instance(
            KcaCertificationEligibilityPolicy::class,
            new class implements KcaCertificationEligibilityPolicy
            {
                public function decide(KcaEnrollment $enrollment): KcaCertificationEligibilityDecision
                {
                    return KcaCertificationEligibilityDecision::approved('approved_by_test_policy');
                }
            },
        );
    }
}
