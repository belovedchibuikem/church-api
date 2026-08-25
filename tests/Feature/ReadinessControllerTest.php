<?php

namespace Tests\Feature;

use App\Support\Health\ReadinessChecker;
use App\Support\Health\ReadinessResult;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReadinessControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_200_when_required_dependencies_are_ready(): void
    {
        $response = $this->getJson('/api/v1/health/readiness');

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.checks.database', 'ok')
            ->assertJsonPath('data.checks.cache', 'ok')
            ->assertJsonPath('data.checks.queue', 'ok')
            ->assertJsonStructure([
                'data' => ['status', 'checks' => ['database', 'cache', 'queue']],
                'meta' => ['api_version', 'timestamp'],
                'correlation_id',
            ]);
    }

    public function test_returns_503_with_safe_component_statuses_when_a_dependency_is_unavailable(): void
    {
        $this->app->instance(
            ReadinessChecker::class,
            new FixedReadinessChecker(new ReadinessResult(false, [
                'database' => 'ok',
                'cache' => 'failed',
                'queue' => 'ok',
            ])),
        );

        $response = $this->getJson('/api/v1/health/readiness');

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('error.code', 'SERVICE_NOT_READY')
            ->assertJsonPath('error.details.checks.cache', 'failed')
            ->assertJsonMissingPath('data')
            ->assertJsonMissingPath('exception');
    }
}

final readonly class FixedReadinessChecker implements ReadinessChecker
{
    public function __construct(private ReadinessResult $result) {}

    public function check(): ReadinessResult
    {
        return $this->result;
    }
}
