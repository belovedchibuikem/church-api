<?php

namespace Tests\Unit;

use JsonException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ProtectedOpenApiContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    /** @throws JsonException */
    public function test_contract_exactly_matches_registered_identity_user_and_admin_operations(): void
    {
        $contract = $this->json('openapi/protected-v1.openapi.json');
        $operations = $this->configuration()['operations'];
        $contractOperations = [];

        foreach ($contract['paths'] as $path => $pathItem) {
            foreach ($pathItem as $method => $operation) {
                $contractOperations[$operation['operationId']] = [
                    'method' => strtoupper($method),
                    'path' => $path,
                ];
            }
        }

        $this->assertSame('3.1.0', $contract['openapi']);
        $this->assertSame($operations, $contractOperations);

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
            $name = (string) $route['name'];
            if (preg_match('/\Aapi\.v1\.(auth|mobile|user|admin)\./', $name) !== 1) {
                continue;
            }

            $registered[] = [
                'method' => str_contains((string) $route['method'], 'GET') ? 'GET' : (string) $route['method'],
                'path' => '/'.ltrim((string) $route['uri'], '/'),
            ];
        }

        $this->assertEqualsCanonicalizing(array_values($operations), $registered);
    }

    /** @throws JsonException */
    public function test_admin_operations_require_scope_and_generated_clients_are_current(): void
    {
        $contract = $this->json('openapi/protected-v1.openapi.json');

        foreach ($contract['paths'] as $path => $pathItem) {
            foreach ($pathItem as $operation) {
                $this->assertNotEmpty($operation['responses']);

                if (! str_starts_with($path, '/api/v1/admin/')) {
                    continue;
                }

                $references = array_column($operation['parameters'], '$ref');
                $this->assertContains('#/components/parameters/ScopeType', $references);
                $this->assertContains('#/components/parameters/ScopeId', $references);
                $this->assertNotEmpty($operation['security']);
            }
        }

        $generator = new Process([PHP_BINARY, 'scripts/generate-protected-api.php', '--check'], $this->root);
        $generator->mustRun();

        $typescript = (string) file_get_contents($this->root.'/clients/typescript/src/protected-api.ts');
        $dart = (string) file_get_contents($this->root.'/clients/dart/lib/protected_api.dart');

        foreach ($this->configuration()['operations'] as $operationId => $operation) {
            $this->assertStringContainsString($operationId, $typescript);
            $this->assertStringContainsString($operationId, $dart);
        }
    }

    /** @throws JsonException */
    public function test_new_organization_and_platform_operations_use_specific_contract_schemas(): void
    {
        $contract = $this->json('openapi/protected-v1.openapi.json');
        $configuration = $this->configuration();

        foreach ($configuration['operationSchemas'] as $operationId => $schemas) {
            $operationDefinition = null;

            foreach ($contract['paths'] as $pathItem) {
                foreach ($pathItem as $candidate) {
                    if ($candidate['operationId'] === $operationId) {
                        $operationDefinition = $candidate;
                        break 2;
                    }
                }
            }

            $this->assertNotNull($operationDefinition, "Missing operation {$operationId}.");
            $successResponse = $operationDefinition['responses']['200']
                ?? $operationDefinition['responses']['201'];
            $this->assertSame(
                '#/components/schemas/'.$schemas['response'],
                $successResponse['content']['application/json']['schema']['allOf'][1]['properties']['data']['$ref'],
            );

            if (isset($schemas['request'])) {
                $this->assertSame(
                    '#/components/schemas/'.$schemas['request'],
                    $operationDefinition['requestBody']['content']['application/json']['schema']['$ref'],
                );
            }
        }
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        return $this->json('clients/protected-client-generation.json');
    }

    /** @return array<string, mixed> */
    private function json(string $path): array
    {
        return json_decode(
            (string) file_get_contents($this->root.'/'.$path),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
