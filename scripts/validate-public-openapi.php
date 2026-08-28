<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$contractPath = $root.'/openapi/public-v1.openapi.json';
$legacyContractPath = $root.'/openapi/openapi.yaml';
$configurationPath = $root.'/clients/public-client-generation.json';
$errors = [];

if (is_file($legacyContractPath)) {
    $errors[] = 'openapi/openapi.yaml must not exist; public-v1.openapi.json is the single authoritative contract.';
}

try {
    /** @var array<string, mixed> $contract */
    $contract = json_decode((string) file_get_contents($contractPath), true, 512, JSON_THROW_ON_ERROR);
    /** @var array{operations?: array<string, array{method: string, path: string}>} $configuration */
    $configuration = json_decode((string) file_get_contents($configurationPath), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, "Invalid JSON: {$exception->getMessage()}\n");
    exit(1);
}

$expectedOperations = $configuration['operations'] ?? [];
$paths = $contract['paths'] ?? null;

if (($contract['openapi'] ?? null) !== '3.1.0') {
    $errors[] = 'The contract must declare OpenAPI 3.1.0.';
}

if (($contract['security'] ?? null) !== []) {
    $errors[] = 'The public contract must not declare authentication.';
}

if (! is_array($paths) || array_keys($paths) !== array_values(array_unique(array_column($expectedOperations, 'path')))) {
    $errors[] = 'The contract paths do not exactly match the configured public operations.';
} else {
    foreach ($expectedOperations as $operationId => $expectedOperation) {
        $method = strtolower($expectedOperation['method']);
        $path = $expectedOperation['path'];
        $operation = $paths[$path][$method] ?? null;

        if (! is_array($operation)) {
            $errors[] = "{$expectedOperation['method']} {$path} is missing.";

            continue;
        }

        if (($operation['operationId'] ?? null) !== $operationId) {
            $errors[] = "{$expectedOperation['method']} {$path} has an unexpected operationId.";
        }

        if (($operation['parameters'][0]['$ref'] ?? null) !== '#/components/parameters/CorrelationId') {
            $errors[] = "{$operationId} must accept the shared correlation ID header first.";
        }

        $successfulResponses = array_filter(
            $operation['responses'] ?? [],
            fn (mixed $response, int|string $status): bool => is_array($response) && preg_match('/^2[0-9]{2}$/', (string) $status) === 1,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($successfulResponses === []) {
            $errors[] = "{$operationId} must define at least one successful response.";
        }
    }

    foreach ($paths as $path => $pathItem) {
        $configuredMethods = [];
        foreach ($expectedOperations as $operation) {
            if ($operation['path'] === $path) {
                $configuredMethods[] = strtolower($operation['method']);
            }
        }

        if (array_keys($pathItem) !== $configuredMethods) {
            $errors[] = "{$path} exposes methods not represented by client generation configuration.";
        }
    }
}

$requiredSchemas = [
    'CorrelationId',
    'ApiMeta',
    'ApiError',
    'ErrorEnvelope',
    'ApiStatusEnvelope',
    'HealthEnvelope',
    'ReadinessEnvelope',
    'ChurchListEnvelope',
    'HomeChurchApplicationEnvelope',
    'PressListEnvelope',
    'EventListEnvelope',
    'CertificateVerificationEnvelope',
    'MissionLocationListEnvelope',
    'MissionCrusadeListEnvelope',
];
$schemas = $contract['components']['schemas'] ?? [];

foreach ($requiredSchemas as $requiredSchema) {
    if (! isset($schemas[$requiredSchema])) {
        $errors[] = "Required shared schema is missing: {$requiredSchema}.";
    }
}

validateReferences($contract, $contract, '$', $errors);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "- {$error}\n");
    }

    exit(1);
}

fwrite(STDOUT, "OpenAPI public contract is valid.\n");

/**
 * @param  array<string, mixed>  $node
 * @param  array<string, mixed>  $root
 * @param  array<int, string>  $errors
 */
function validateReferences(array $node, array $root, string $location, array &$errors): void
{
    foreach ($node as $key => $value) {
        $childLocation = $location.'.'.$key;

        if ($key === '$ref' && is_string($value)) {
            if (! str_starts_with($value, '#/')) {
                $errors[] = "Only internal references are allowed at {$childLocation}.";

                continue;
            }

            $resolved = $root;

            foreach (explode('/', substr($value, 2)) as $segment) {
                $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

                if (! is_array($resolved) || ! array_key_exists($segment, $resolved)) {
                    $errors[] = "Unresolved reference {$value} at {$childLocation}.";

                    continue 2;
                }

                $resolved = $resolved[$segment];
            }

            continue;
        }

        if (is_array($value)) {
            validateReferences($value, $root, $childLocation, $errors);
        }
    }
}
