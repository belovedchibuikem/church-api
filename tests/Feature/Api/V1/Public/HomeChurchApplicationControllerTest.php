<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\AdministrativeUnit;
use App\Models\AuditEvent;
use App\Models\Church;
use App\Models\HomeChurchApplication;
use App\Models\Location;
use App\Models\Person;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HomeChurchApplicationControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_returns_201_and_persists_a_private_idempotent_public_application(): void
    {
        $church = Church::factory()->published()->create();
        $payload = $this->validPayload($church);

        $response = $this->withHeader('Idempotency-Key', 'home-church-public-001')
            ->postJson('/api/v1/home-church-applications', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('meta.idempotent_replay', false)
            ->assertJsonMissingPath('data.contact_email')
            ->assertJsonMissingPath('data.contact_phone')
            ->assertJsonMissingPath('data.applicant_id');
        $application = HomeChurchApplication::query()->sole();
        $this->assertSame('leader@example.test', $application->contact_email);
        $this->assertSame(1, Person::query()->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'home_church.application.created')->count());
        $this->assertNotSame('home-church-public-001', $application->public_idempotency_scope_hash);
        $this->assertArrayNotHasKey('public_idempotency_scope_hash', $application->toArray());
        $this->assertArrayNotHasKey('public_payload_fingerprint', $application->toArray());
    }

    public function test_returns_200_for_an_identical_retry_without_duplicate_people_applications_or_audits(): void
    {
        $church = Church::factory()->published()->create();
        $payload = $this->validPayload($church);
        $created = $this->withHeader('Idempotency-Key', 'home-church-public-002')
            ->postJson('/api/v1/home-church-applications', $payload);

        $retry = $this->withHeader('Idempotency-Key', 'home-church-public-002')
            ->postJson('/api/v1/home-church-applications', $payload);

        $retry
            ->assertOk()
            ->assertJsonPath('data.application_id', $created->json('data.application_id'))
            ->assertJsonPath('meta.idempotent_replay', true);
        $this->assertSame(1, HomeChurchApplication::query()->count());
        $this->assertSame(1, Person::query()->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'home_church.application.created')->count());
    }

    public function test_returns_409_when_an_idempotency_key_is_reused_with_a_different_payload(): void
    {
        $church = Church::factory()->published()->create();
        $payload = $this->validPayload($church);
        $this->withHeader('Idempotency-Key', 'home-church-public-003')
            ->postJson('/api/v1/home-church-applications', $payload)
            ->assertCreated();

        $payload['proposed_name'] = 'Different Home Church';
        $response = $this->withHeader('Idempotency-Key', 'home-church-public-003')
            ->postJson('/api/v1/home-church-applications', $payload);

        $response
            ->assertConflict()
            ->assertJsonPath('error.code', 'RESOURCE_CONFLICT');
        $this->assertSame(1, HomeChurchApplication::query()->count());
        $this->assertSame(1, Person::query()->count());
    }

    public function test_accepts_a_body_idempotency_key_and_rejects_mismatched_header_and_body_keys(): void
    {
        $church = Church::factory()->published()->create();
        $payload = $this->validPayload($church);
        $payload['idempotency_key'] = 'home-church-body-001';

        $this->postJson('/api/v1/home-church-applications', $payload)->assertCreated();

        $payload['idempotency_key'] = 'home-church-body-002';
        $this->withHeader('Idempotency-Key', 'home-church-header-002')
            ->postJson('/api/v1/home-church-applications', $payload)
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.details.fields.idempotency_key.0',
                'The Idempotency-Key header and idempotency_key field must match when both are provided.',
            );
    }

    public function test_returns_422_and_rolls_back_identity_when_location_scope_is_invalid(): void
    {
        $church = Church::factory()->published()->create();
        $payload = $this->validPayload($church);
        $otherUnit = AdministrativeUnit::factory()->create();
        $payload['administrative_unit_id'] = $otherUnit->public_id;
        $personCount = Person::query()->count();

        $response = $this->withHeader('Idempotency-Key', 'home-church-public-004')
            ->postJson('/api/v1/home-church-applications', $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertSame(0, HomeChurchApplication::query()->count());
        $this->assertSame($personCount, Person::query()->count());
    }

    public function test_returns_422_for_unknown_fields_without_persisting_contact_data(): void
    {
        $church = Church::factory()->published()->create();
        $payload = [...$this->validPayload($church), 'status' => 'active'];

        $response = $this->withHeader('Idempotency-Key', 'home-church-public-005')
            ->postJson('/api/v1/home-church-applications', $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertSame(0, HomeChurchApplication::query()->count());
    }

    /** @return array<string, mixed> */
    private function validPayload(Church $church): array
    {
        $location = Location::query()->findOrFail($church->location_id);
        $unit = AdministrativeUnit::query()->findOrFail($church->administrative_unit_id);

        return [
            'church_id' => $church->public_id,
            'location_id' => $location->public_id,
            'administrative_unit_id' => $unit->public_id,
            'applicant' => [
                'given_name' => 'Ama',
                'middle_name' => null,
                'family_name' => 'Mensah',
                'preferred_name' => 'Ama',
            ],
            'proposed_name' => 'Grace Street Home Church',
            'expected_participants' => 12,
            'meeting_day' => 'saturday',
            'meeting_time' => '17:30',
            'contact_email' => 'leader@example.test',
            'contact_phone' => '+233 20 123 4567',
            'guidelines_agreed' => true,
        ];
    }
}
