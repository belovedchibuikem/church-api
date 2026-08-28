<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Models\KcaCertificate;
use App\Support\Kca\KcaCertificateCodeHasher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VerifyKcaCertificateControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_returns_only_public_certificate_verification_facts_for_valid_code(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01 10:00:00 UTC'));
        $verificationCode = '01K4E6X6BWFTPHH0W3JZ9R7C1A';
        KcaCertificate::factory()->create([
            'certificate_number' => 'KCA-CERT-2026-0001',
            'completion_on' => '2026-08-30',
            'issued_at' => '2026-08-31 12:00:00',
            'verification_code_hash' => $this->app
                ->make(KcaCertificateCodeHasher::class)
                ->hash($verificationCode),
        ]);

        $response = $this->getJson('/api/v1/kca/certificates/verify?code='.$verificationCode);

        $response
            ->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.certificate_number', 'KCA-CERT-2026-0001')
            ->assertJsonPath('data.completion_on', '2026-08-30')
            ->assertJsonPath('data.issued_at', '2026-08-31T12:00:00+00:00')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.verification_code_hash')
            ->assertJsonMissingPath('data.issuance_key_hash')
            ->assertJsonMissingPath('data.person_id')
            ->assertJsonMissingPath('data.kca_enrollment_id')
            ->assertJsonMissingPath('data.issued_by_user_id');
        $this->assertSame(
            ['verified', 'certificate_number', 'completion_on', 'issued_at', 'revoked', 'revoked_at'],
            array_keys($response->json('data')),
        );
    }

    public function test_returns_same_safe_404_for_unknown_and_malformed_codes(): void
    {
        $unknown = $this->getJson(
            '/api/v1/kca/certificates/verify?code=01K4E6X6BWFTPHH0W3JZ9R7C1B',
        );
        $malformed = $this->getJson('/api/v1/kca/certificates/verify?code=not-a-code');

        $unknown
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonPath('error.message', 'The requested resource was not found.')
            ->assertJsonMissingPath('data');
        $malformed
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonPath('error.message', 'The requested resource was not found.')
            ->assertJsonMissingPath('data');
    }

    public function test_returns_422_when_verification_code_is_missing(): void
    {
        $response = $this->getJson('/api/v1/kca/certificates/verify');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.fields.code.0', 'The code field is required.');
    }

    public function test_returns_422_for_unexpected_query_parameter(): void
    {
        $response = $this->getJson(
            '/api/v1/kca/certificates/verify?code=not-a-code&include=student',
        );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.query.0', 'Unsupported query parameter: include.');
    }

    public function test_returns_429_after_certificate_verification_limit_is_exceeded(): void
    {
        config()->set('api.rate_limits.certificate_verification_per_minute', 2);

        for ($requestNumber = 1; $requestNumber <= 2; $requestNumber++) {
            $this->getJson('/api/v1/kca/certificates/verify?code=not-a-code')->assertNotFound();
        }

        $this->getJson('/api/v1/kca/certificates/verify?code=not-a-code')
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED')
            ->assertHeader('Retry-After');
    }
}
