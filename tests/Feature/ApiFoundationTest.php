<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    public function test_public_api_status_returns_versioned_envelope(): void
    {
        $response = $this->getJson('/api/v1');

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Family House Connect API')
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('meta.api_version', 'v1')
            ->assertJsonStructure([
                'data' => ['name', 'status', 'surfaces'],
                'meta' => ['api_version', 'timestamp'],
                'correlation_id',
            ]);
    }

    public function test_public_health_returns_ok_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.service', 'family-house-connect-api');
    }

    public function test_valid_correlation_id_is_propagated(): void
    {
        $correlationId = '0e984725-c51c-4bf4-9960-e1c80e27aba0';

        $response = $this->withHeader('X-Correlation-ID', $correlationId)
            ->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertHeader('X-Correlation-ID', $correlationId)
            ->assertJsonPath('correlation_id', $correlationId);
    }

    public function test_invalid_correlation_id_is_replaced(): void
    {
        $response = $this->withHeader('X-Correlation-ID', 'unsafe-value')
            ->getJson('/api/v1/health');

        $generatedCorrelationId = $response->headers->get('X-Correlation-ID');

        $response->assertOk();
        $this->assertTrue(Str::isUuid($generatedCorrelationId));
        $this->assertSame($generatedCorrelationId, $response->json('correlation_id'));
    }
}
