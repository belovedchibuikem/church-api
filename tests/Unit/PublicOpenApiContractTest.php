<?php

namespace Tests\Unit;

use JsonException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class PublicOpenApiContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    /** @throws JsonException */
    public function test_contract_exactly_matches_registered_public_operations(): void
    {
        $contract = $this->contract();
        $operations = $this->configuredOperations();

        $this->assertSame('3.1.0', $contract['openapi']);
        $this->assertSame([], $contract['security']);
        $this->assertSame(
            array_values(array_unique(array_column($operations, 'path'))),
            array_keys($contract['paths']),
        );

        foreach ($operations as $operationId => $operation) {
            $contractOperation = $contract['paths'][$operation['path']][strtolower($operation['method'])];
            $this->assertSame($operationId, $contractOperation['operationId']);
        }

        $routeProcess = new Process([
            PHP_BINARY,
            'artisan',
            'route:list',
            '--json',
            '--path=api/v1',
            '--except-vendor',
        ], $this->root);
        $routeProcess->mustRun();
        $routes = json_decode($routeProcess->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $registered = [];

        foreach ($routes as $route) {
            $routeName = (string) $route['name'];

            if (
                ! str_starts_with($routeName, 'api.v1.')
                || str_starts_with($routeName, 'api.v1.auth.')
                || str_starts_with($routeName, 'api.v1.mobile.')
                || str_starts_with($routeName, 'api.v1.user.')
                || str_starts_with($routeName, 'api.v1.admin.')
            ) {
                continue;
            }

            $method = str_contains((string) $route['method'], 'GET') ? 'GET' : (string) $route['method'];
            $registered[] = ['method' => $method, 'path' => '/'.ltrim((string) $route['uri'], '/')];
        }

        $this->assertEqualsCanonicalizing(array_values($operations), $registered);
    }

    /** @throws JsonException */
    public function test_contract_uses_shared_envelopes_and_correlation_ids(): void
    {
        $contract = $this->contract();
        $schemas = $contract['components']['schemas'];

        $this->assertSame('uuid', $schemas['CorrelationId']['format']);
        $this->assertSame(['error', 'meta', 'correlation_id'], $schemas['ErrorEnvelope']['required']);

        foreach ($contract['paths'] as $pathItem) {
            foreach ($pathItem as $operation) {
                $this->assertSame(
                    '#/components/parameters/CorrelationId',
                    $operation['parameters'][0]['$ref'],
                );
                $successfulResponses = array_filter(
                    $operation['responses'],
                    fn (int|string $status): bool => preg_match('/^2[0-9]{2}$/', (string) $status) === 1,
                    ARRAY_FILTER_USE_KEY,
                );
                $this->assertNotEmpty($successfulResponses);
            }
        }
    }

    /** @throws JsonException */
    public function test_contract_validation_and_generated_clients_are_current(): void
    {
        $validator = new Process([PHP_BINARY, 'scripts/validate-public-openapi.php'], $this->root);
        $validator->run();
        $this->assertTrue($validator->isSuccessful(), $validator->getErrorOutput());

        $generator = new Process([PHP_BINARY, 'scripts/generate-public-api-clients.php', '--check'], $this->root);
        $generator->run();
        $this->assertTrue($generator->isSuccessful(), $generator->getErrorOutput());

        $operationIds = array_keys($this->configuredOperations());
        foreach (['clients/typescript/src/public-api.ts', 'clients/dart/lib/public_api.dart'] as $clientPath) {
            $client = (string) file_get_contents($this->root.'/'.$clientPath);
            foreach ($operationIds as $operationId) {
                $this->assertStringContainsString($operationId, $client);
            }
            $this->assertStringNotContainsString('/api/v1/user', $client);
            $this->assertStringNotContainsString('/api/v1/admin', $client);
        }
    }

    /** @return array<string, array{method: string, path: string}> */
    private function configuredOperations(): array
    {
        $configuration = json_decode(
            (string) file_get_contents($this->root.'/clients/public-client-generation.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return $configuration['operations'];
    }

    /** @return array<string, mixed> */
    private function contract(): array
    {
        return json_decode(
            (string) file_get_contents($this->root.'/openapi/public-v1.openapi.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
