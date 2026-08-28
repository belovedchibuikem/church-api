<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

/** @return array<string, mixed> */
function readJson(string $path, array &$failures): array
{
    try {
        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        $failures[] = "Invalid JSON at {$path}: {$exception->getMessage()}";

        return [];
    }
}

$requiredFiles = [
    'clients/typescript/package.json',
    'clients/typescript/tsconfig.json',
    'clients/typescript/.env.example',
    'clients/typescript/examples/nextjs-public-health.ts',
    'clients/dart/pubspec.yaml',
    'clients/dart/analysis_options.yaml',
    'clients/dart/dart-define.example.json',
    'clients/dart/lib/io_public_api_transport.dart',
    'clients/dart/example/flutter_public_health.dart',
    'clients/INTEGRATION.md',
];

foreach ($requiredFiles as $relativePath) {
    if (! is_file($root.'/'.$relativePath)) {
        $failures[] = "Missing integration file: {$relativePath}";
    }
}

$typescriptPackage = readJson($root.'/clients/typescript/package.json', $failures);
$typescriptConfiguration = readJson($root.'/clients/typescript/tsconfig.json', $failures);
$dartDefines = readJson($root.'/clients/dart/dart-define.example.json', $failures);

if (($typescriptPackage['private'] ?? null) !== true || ($typescriptPackage['scripts']['typecheck'] ?? null) !== 'tsc --noEmit') {
    $failures[] = 'The TypeScript client must remain private and expose the deterministic typecheck script.';
}

if (($typescriptConfiguration['compilerOptions']['strict'] ?? null) !== true) {
    $failures[] = 'The TypeScript integration must enable strict type checking.';
}

if (! is_string($dartDefines['FHC_PUBLIC_API_BASE_URL'] ?? null)) {
    $failures[] = 'The Dart define example must configure FHC_PUBLIC_API_BASE_URL.';
}

$contentRequirements = [
    'clients/typescript/examples/nextjs-public-health.ts' => [
        'NEXT_PUBLIC_FHC_API_BASE_URL',
        'getApiStatus',
        'getHealth',
        'getReadiness',
        'PublicApiError',
        'correlation_id',
    ],
    'clients/dart/example/flutter_public_health.dart' => [
        'FHC_PUBLIC_API_BASE_URL',
        'getApiStatus',
        'getHealth',
        'getReadiness',
        'PublicApiException',
        'correlationId',
    ],
    'clients/dart/lib/io_public_api_transport.dart' => [
        'implements PublicApiTransport',
        'headers.forEach',
        'statusCode',
    ],
];

foreach ($contentRequirements as $relativePath => $needles) {
    $content = (string) file_get_contents($root.'/'.$relativePath);

    foreach ($needles as $needle) {
        if (! str_contains($content, $needle)) {
            $failures[] = "{$relativePath} is missing required integration marker: {$needle}";
        }
    }

    foreach (['/api/v1/user', '/api/v1/admin', 'Authorization', 'Bearer ', 'Cookie'] as $forbidden) {
        if (str_contains($content, $forbidden)) {
            $failures[] = "{$relativePath} contains protected transport behavior: {$forbidden}";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Public client integration scaffolding is valid.\n");
