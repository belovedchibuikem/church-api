<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$configurationPath = $root.'/clients/protected-client-generation.json';
$checkOnly = in_array('--check', $argv, true);

try {
    /** @var array{contract: string, operations: array<string, array{method: string, path: string}>, operationSchemas?: array<string, array{request?: string, response?: string}>, outputs: array{typescript: string, dart: string}} $configuration */
    $configuration = json_decode((string) file_get_contents($configurationPath), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, "Protected client configuration is invalid: {$exception->getMessage()}\n");
    exit(1);
}

$operationSchemas = $configuration['operationSchemas'] ?? [];
$contract = buildContract($configuration['operations'], $operationSchemas);
$contractContents = json_encode($contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
$digest = hash('sha256', $contractContents);
$outputs = [
    $configuration['contract'] => $contractContents,
    $configuration['outputs']['typescript'] => renderTypeScript($digest, $configuration['operations'], $operationSchemas),
    $configuration['outputs']['dart'] => renderDart($digest, $configuration['operations']),
];
$stale = false;

foreach ($outputs as $relativePath => $contents) {
    $path = $root.'/'.$relativePath;

    if ($checkOnly) {
        if (! is_file($path) || file_get_contents($path) !== $contents) {
            fwrite(STDERR, "Generated protected artifact is stale: {$relativePath}\n");
            $stale = true;
        }

        continue;
    }

    if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
        fwrite(STDERR, "Unable to create output directory for {$relativePath}.\n");
        exit(1);
    }

    file_put_contents($path, $contents);
    fwrite(STDOUT, "Generated {$relativePath}\n");
}

exit($stale ? 1 : 0);

/**
 * @param  array<string, array{method: string, path: string}>  $operations
 * @param  array<string, array{request?: string, response?: string}>  $operationSchemas
 * @return array<string, mixed>
 */
function buildContract(array $operations, array $operationSchemas): array
{
    $paths = [];

    foreach ($operations as $operationId => $operation) {
        $method = strtolower($operation['method']);
        $parameters = [[
            'name' => 'X-Correlation-ID',
            'in' => 'header',
            'required' => false,
            'schema' => ['$ref' => '#/components/schemas/CorrelationId'],
        ]];

        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $operation['path'], $matches);
        foreach ($matches[1] as $parameter) {
            $parameters[] = [
                'name' => $parameter,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ];
        }

        if (str_starts_with($operation['path'], '/api/v1/admin/')) {
            $parameters[] = ['$ref' => '#/components/parameters/ScopeType'];
            $parameters[] = ['$ref' => '#/components/parameters/ScopeId'];
        }

        $status = operationSuccessStatus($operationId)
            ? '201'
            : '200';
        $responseSchema = isset($operationSchemas[$operationId]['response'])
            ? [
                'allOf' => [
                    ['$ref' => '#/components/schemas/SuccessEnvelope'],
                    ['properties' => ['data' => ['$ref' => '#/components/schemas/'.$operationSchemas[$operationId]['response']]]],
                ],
            ]
            : ['$ref' => '#/components/schemas/SuccessEnvelope'];
        $successResponse = operationIsFileStream($operationId)
            ? [
                'description' => 'The file asset stream.',
                'headers' => [
                    'Content-Disposition' => [
                        'required' => true,
                        'schema' => ['type' => 'string'],
                        'description' => 'inline or attachment disposition with a sanitized filename.',
                    ],
                ],
                'content' => [
                    'application/octet-stream' => [
                        'schema' => [
                            'type' => 'string',
                            'format' => 'binary',
                        ],
                    ],
                ],
            ]
            : [
                'description' => 'Successful response.',
                'content' => ['application/json' => ['schema' => $responseSchema]],
            ];
        $contractOperation = [
            'operationId' => $operationId,
            'tags' => [operationTag($operation['path'])],
            'parameters' => $parameters,
            'security' => operationSecurity($operationId, $operation['path'], $operation['method']),
            'responses' => [
                $status => $successResponse,
                'default' => [
                    'description' => 'Normalized API error.',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
                ],
            ],
        ];

        if (operationHasRequestBody($operationId, $operation['method'])) {
            $contractOperation['requestBody'] = [
                'required' => true,
                'content' => ['application/json' => ['schema' => [
                    '$ref' => '#/components/schemas/'.($operationSchemas[$operationId]['request'] ?? 'JsonObject'),
                ]]],
            ];
        }

        $paths[$operation['path']][$method] = $contractOperation;
    }

    return [
        'openapi' => '3.1.0',
        'info' => [
            'title' => 'Family House Connect Identity, User and Admin API',
            'version' => '1.0.0',
        ],
        'paths' => $paths,
        'components' => [
            'securitySchemes' => [
                'cookieSession' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => 'family_house_connect_session'],
                'csrfToken' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-XSRF-TOKEN'],
                'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'opaque'],
            ],
            'parameters' => [
                'ScopeType' => ['name' => 'X-Scope-Type', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']],
                'ScopeId' => ['name' => 'X-Scope-ID', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']],
            ],
            'schemas' => [
                'CorrelationId' => ['type' => 'string', 'format' => 'uuid'],
                'JsonObject' => ['type' => 'object', 'additionalProperties' => true],
                'SuccessEnvelope' => [
                    'type' => 'object',
                    'required' => ['data', 'meta', 'correlation_id'],
                    'properties' => [
                        'data' => true,
                        'meta' => ['$ref' => '#/components/schemas/ApiMeta'],
                        'correlation_id' => ['$ref' => '#/components/schemas/CorrelationId'],
                    ],
                ],
                'ApiMeta' => [
                    'type' => 'object',
                    'required' => ['api_version', 'timestamp'],
                    'properties' => [
                        'api_version' => ['type' => 'string', 'const' => 'v1'],
                        'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                    'additionalProperties' => true,
                ],
                'ErrorEnvelope' => [
                    'type' => 'object',
                    'required' => ['error', 'meta', 'correlation_id'],
                    'properties' => [
                        'error' => [
                            'type' => 'object',
                            'required' => ['code', 'message', 'details'],
                            'properties' => [
                                'code' => ['type' => 'string'],
                                'message' => ['type' => 'string'],
                                'details' => ['$ref' => '#/components/schemas/JsonObject'],
                            ],
                        ],
                        'meta' => ['$ref' => '#/components/schemas/ApiMeta'],
                        'correlation_id' => ['$ref' => '#/components/schemas/CorrelationId'],
                    ],
                ],
                ...protectedDomainSchemas(),
            ],
        ],
    ];
}

/** @return array<string, array<string, mixed>> */
function protectedDomainSchemas(): array
{
    $opaqueId = ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'];
    $nullableString = ['type' => ['string', 'null']];
    $scope = [
        'type' => ['object', 'null'],
        'required' => ['type', 'id'],
        'properties' => [
            'type' => ['type' => 'string'],
            'id' => ['type' => 'string'],
        ],
        'additionalProperties' => false,
    ];
    $reference = [
        'type' => 'object',
        'required' => ['id', 'name'],
        'properties' => ['id' => $opaqueId, 'name' => ['type' => 'string']],
        'additionalProperties' => false,
    ];

    return [
        'AdminCountry' => [
            'type' => 'object',
            'required' => ['id', 'iso_code', 'name', 'created_at'],
            'properties' => [
                'id' => $opaqueId,
                'iso_code' => ['type' => 'string', 'pattern' => '^[A-Z]{2}$'],
                'name' => ['type' => 'string'],
                'created_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'AdminCountryList' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AdminCountry']],
        'CreateAdminCountryInput' => [
            'type' => 'object',
            'required' => ['iso_code', 'name'],
            'properties' => [
                'iso_code' => ['type' => 'string', 'pattern' => '^[A-Za-z]{2}$'],
                'name' => ['type' => 'string', 'maxLength' => 191],
            ],
            'additionalProperties' => false,
        ],
        'AdminAdministrativeLevel' => [
            'type' => 'object',
            'required' => ['id', 'country_id', 'code', 'name', 'sort_order'],
            'properties' => [
                'id' => $opaqueId,
                'country_id' => $opaqueId,
                'code' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'sort_order' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 65535],
            ],
            'additionalProperties' => false,
        ],
        'AdminAdministrativeLevelList' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AdminAdministrativeLevel']],
        'CreateAdminAdministrativeLevelInput' => [
            'type' => 'object',
            'required' => ['code', 'name', 'sort_order'],
            'properties' => [
                'code' => ['type' => 'string', 'maxLength' => 100],
                'name' => ['type' => 'string', 'maxLength' => 191],
                'sort_order' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 65535],
            ],
            'additionalProperties' => false,
        ],
        'AdminAdministrativeUnit' => [
            'type' => 'object',
            'required' => ['id', 'name', 'reference_code', 'country', 'administrative_level', 'parent', 'created_at'],
            'properties' => [
                'id' => $opaqueId,
                'name' => ['type' => 'string'],
                'reference_code' => $nullableString,
                'country' => ['$ref' => '#/components/schemas/AdminCountryReference'],
                'administrative_level' => ['$ref' => '#/components/schemas/AdminAdministrativeLevelReference'],
                'parent' => ['oneOf' => [$reference, ['type' => 'null']]],
                'created_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'AdminAdministrativeUnitList' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AdminAdministrativeUnit']],
        'AdminCountryReference' => [
            'type' => 'object',
            'required' => ['id', 'iso_code', 'name'],
            'properties' => [
                'id' => $opaqueId,
                'iso_code' => ['type' => 'string'],
                'name' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ],
        'AdminAdministrativeLevelReference' => [
            'type' => 'object',
            'required' => ['id', 'code', 'name', 'sort_order'],
            'properties' => [
                'id' => $opaqueId,
                'code' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'sort_order' => ['type' => 'integer'],
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminAdministrativeUnitInput' => [
            'type' => 'object',
            'required' => ['country_id', 'administrative_level_id', 'name'],
            'properties' => [
                'country_id' => $opaqueId,
                'administrative_level_id' => $opaqueId,
                'parent_id' => ['oneOf' => [$opaqueId, ['type' => 'null']]],
                'name' => ['type' => 'string', 'maxLength' => 191],
                'reference_code' => ['type' => ['string', 'null'], 'maxLength' => 100],
            ],
            'additionalProperties' => false,
        ],
        'MoveAdminAdministrativeUnitInput' => [
            'type' => 'object',
            'required' => ['parent_id'],
            'properties' => ['parent_id' => ['oneOf' => [$opaqueId, ['type' => 'null']]],
            ],
            'additionalProperties' => false,
        ],
        'AdminLocation' => [
            'type' => 'object',
            'required' => ['id', 'name', 'country', 'administrative_unit', 'address', 'timezone', 'coordinates', 'created_at'],
            'properties' => [
                'id' => $opaqueId,
                'name' => ['type' => 'string'],
                'country' => ['$ref' => '#/components/schemas/AdminCountryReference'],
                'administrative_unit' => ['oneOf' => [$reference, ['type' => 'null']]],
                'address' => ['$ref' => '#/components/schemas/AdminAddress'],
                'timezone' => ['type' => 'string'],
                'coordinates' => [
                    'oneOf' => [
                        [
                            'type' => 'object',
                            'required' => ['latitude', 'longitude'],
                            'properties' => [
                                'latitude' => ['type' => 'number', 'minimum' => -90, 'maximum' => 90],
                                'longitude' => ['type' => 'number', 'minimum' => -180, 'maximum' => 180],
                            ],
                            'additionalProperties' => false,
                        ],
                        ['type' => 'null'],
                    ],
                ],
                'created_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'AdminLocationList' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AdminLocation']],
        'AdminAddress' => [
            'type' => 'object',
            'required' => ['line_one', 'line_two', 'locality', 'postal_code'],
            'properties' => [
                'line_one' => $nullableString,
                'line_two' => $nullableString,
                'locality' => $nullableString,
                'postal_code' => $nullableString,
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminLocationInput' => [
            'type' => 'object',
            'required' => ['country_id', 'name', 'timezone'],
            'properties' => [
                'country_id' => $opaqueId,
                'administrative_unit_id' => ['oneOf' => [$opaqueId, ['type' => 'null']]],
                'name' => ['type' => 'string', 'maxLength' => 191],
                'address_line_one' => ['type' => ['string', 'null'], 'maxLength' => 191],
                'address_line_two' => ['type' => ['string', 'null'], 'maxLength' => 191],
                'locality' => ['type' => ['string', 'null'], 'maxLength' => 191],
                'postal_code' => ['type' => ['string', 'null'], 'maxLength' => 32],
                'timezone' => ['type' => 'string'],
                'latitude' => ['type' => ['number', 'null'], 'minimum' => -90, 'maximum' => 90],
                'longitude' => ['type' => ['number', 'null'], 'minimum' => -180, 'maximum' => 180],
            ],
            'additionalProperties' => false,
        ],
        'AdminPlatformConfiguration' => [
            'type' => 'object',
            'required' => ['id', 'key', 'value_type', 'classification', 'environment', 'scope', 'value', 'has_value', 'updated_at'],
            'properties' => [
                'id' => $opaqueId,
                'key' => ['type' => 'string'],
                'value_type' => ['enum' => ['string', 'integer', 'boolean', 'json']],
                'classification' => ['enum' => ['internal', 'confidential']],
                'environment' => ['type' => 'string'],
                'scope' => $scope,
                'value' => ['$ref' => '#/components/schemas/PlatformConfigurationValue'],
                'has_value' => ['type' => 'boolean'],
                'updated_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'AdminPlatformConfigurationList' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AdminPlatformConfiguration']],
        'PlatformConfigurationValue' => [
            'oneOf' => [
                ['type' => 'string'], ['type' => 'integer'], ['type' => 'boolean'],
                ['type' => 'object', 'additionalProperties' => true], ['type' => 'array'], ['type' => 'null'],
            ],
        ],
        'UpsertAdminPlatformConfigurationInput' => [
            'type' => 'object',
            'required' => ['key', 'value_type', 'classification', 'value', 'environment'],
            'properties' => [
                'key' => ['type' => 'string', 'maxLength' => 191],
                'value_type' => ['enum' => ['string', 'integer', 'boolean', 'json']],
                'classification' => ['enum' => ['internal', 'confidential']],
                'value' => ['$ref' => '#/components/schemas/PlatformConfigurationValue'],
                'environment' => ['type' => 'string', 'maxLength' => 50],
                'scope_type' => ['type' => ['string', 'null']],
                'scope_id' => ['type' => ['string', 'null']],
            ],
            'additionalProperties' => false,
        ],
        'AdminFeatureFlag' => [
            'type' => 'object',
            'required' => ['id', 'key', 'environment', 'scope', 'enabled', 'rollout_percentage', 'starts_at', 'ends_at', 'updated_at'],
            'properties' => [
                'id' => $opaqueId,
                'key' => ['type' => 'string'],
                'environment' => ['type' => 'string'],
                'scope' => $scope,
                'enabled' => ['type' => 'boolean'],
                'rollout_percentage' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'starts_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                'ends_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                'updated_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'AdminFeatureFlagList' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AdminFeatureFlag']],
        'UpsertAdminFeatureFlagInput' => [
            'type' => 'object',
            'required' => ['key', 'environment', 'rollout_percentage'],
            'properties' => [
                'key' => ['type' => 'string', 'maxLength' => 191],
                'environment' => ['type' => 'string', 'maxLength' => 50],
                'scope_type' => ['type' => ['string', 'null']],
                'scope_id' => ['type' => ['string', 'null']],
                'rollout_percentage' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'starts_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                'ends_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'AdminObjectStorageStatus' => [
            'type' => 'object',
            'required' => ['provider', 'configured', 'active', 'credentials_configured'],
            'properties' => [
                'provider' => ['type' => 'string', 'const' => 's3'],
                'configured' => ['type' => 'boolean'],
                'active' => ['type' => 'boolean'],
                'credentials_configured' => ['type' => 'boolean'],
                'active_provider' => ['enum' => ['local', 's3']],
                'region' => ['type' => ['string', 'null']],
                'bucket' => ['type' => ['string', 'null']],
                'endpoint' => ['type' => ['string', 'null'], 'format' => 'uri'],
                'url' => ['type' => ['string', 'null'], 'format' => 'uri'],
                'root_prefix' => ['type' => ['string', 'null']],
                'use_path_style_endpoint' => ['type' => 'boolean'],
                'configuration_revision' => ['type' => 'integer', 'minimum' => 1],
                'validation' => ['type' => 'object', 'additionalProperties' => true],
                'validation_result' => ['type' => 'object', 'additionalProperties' => true],
                'activated_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'ConfigureAdminObjectStorageInput' => [
            'type' => 'object',
            'required' => ['access_key_id', 'secret_access_key', 'region', 'bucket'],
            'properties' => [
                'access_key_id' => ['type' => 'string', 'maxLength' => 255, 'writeOnly' => true],
                'secret_access_key' => ['type' => 'string', 'maxLength' => 2048, 'writeOnly' => true],
                'region' => ['type' => 'string', 'maxLength' => 100],
                'bucket' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 191],
                'endpoint' => ['type' => ['string', 'null'], 'format' => 'uri'],
                'url' => ['type' => ['string', 'null'], 'format' => 'uri'],
                'root_prefix' => ['type' => ['string', 'null'], 'maxLength' => 1024],
                'use_path_style_endpoint' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ],
        'AdminObjectStorageDeactivation' => [
            'type' => 'object',
            'required' => ['active_provider', 'object_storage_active'],
            'properties' => [
                'active_provider' => ['type' => 'string', 'const' => 'local'],
                'object_storage_active' => ['type' => 'boolean', 'const' => false],
            ],
            'additionalProperties' => false,
        ],
        'AdminMapsProviderStatus' => [
            'type' => 'object',
            'required' => ['configured', 'active', 'active_provider', 'providers'],
            'properties' => [
                'configured' => ['type' => 'boolean'],
                'active' => ['type' => 'boolean'],
                'active_provider' => ['enum' => ['google', 'mapbox', 'leaflet']],
                'providers' => ['type' => 'object', 'additionalProperties' => true],
                'default_center' => [
                    'type' => 'object',
                    'properties' => [
                        'latitude' => ['type' => 'number'],
                        'longitude' => ['type' => 'number'],
                    ],
                    'additionalProperties' => false,
                ],
                'default_zoom' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 22],
                'configuration_revision' => ['type' => 'integer', 'minimum' => 1],
                'validation' => ['type' => 'object', 'additionalProperties' => true],
                'activated_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'ConfigureAdminMapsProviderInput' => [
            'type' => 'object',
            'required' => ['active_provider'],
            'properties' => [
                'active_provider' => ['enum' => ['google', 'mapbox', 'leaflet']],
                'google_api_key' => ['type' => ['string', 'null'], 'maxLength' => 512, 'writeOnly' => true],
                'mapbox_access_token' => ['type' => ['string', 'null'], 'maxLength' => 512, 'writeOnly' => true],
                'leaflet_tile_url' => ['type' => ['string', 'null'], 'format' => 'uri'],
                'default_latitude' => ['type' => ['number', 'null'], 'minimum' => -90, 'maximum' => 90],
                'default_longitude' => ['type' => ['number', 'null'], 'minimum' => -180, 'maximum' => 180],
                'default_zoom' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 22],
            ],
            'additionalProperties' => false,
        ],
        'AdminMapsProviderDeactivation' => [
            'type' => 'object',
            'required' => ['active', 'active_provider'],
            'properties' => [
                'active' => ['type' => 'boolean', 'const' => false],
                'active_provider' => ['enum' => ['google', 'mapbox', 'leaflet']],
            ],
            'additionalProperties' => false,
        ],
        'UserCapabilities' => [
            'type' => 'object',
            'required' => ['permissions', 'scopes'],
            'properties' => [
                'permissions' => ['type' => 'array', 'items' => ['type' => 'string']],
                'scopes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['type', 'key'],
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'key' => ['type' => 'string'],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'additionalProperties' => false,
        ],
        'CheckUserAuthorizationInput' => [
            'type' => 'object',
            'required' => ['permission'],
            'properties' => [
                'permission' => ['type' => 'string', 'maxLength' => 191],
                'scope_type' => ['type' => ['string', 'null'], 'maxLength' => 100],
                'scope_id' => ['type' => ['string', 'null'], 'maxLength' => 64],
                'resource_id' => ['type' => ['string', 'null'], 'maxLength' => 64],
            ],
            'additionalProperties' => false,
        ],
        'UserAuthorizationDecision' => [
            'type' => 'object',
            'required' => ['allowed', 'state', 'permission', 'canonical_permission', 'reason'],
            'properties' => [
                'allowed' => ['type' => 'boolean'],
                'state' => ['enum' => ['allowed', 'forbidden']],
                'permission' => ['type' => 'string'],
                'canonical_permission' => ['type' => 'string'],
                'reason' => ['type' => 'string'],
                'scope' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string'],
                        'id' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
                'decision_id' => ['type' => ['string', 'null']],
            ],
            'additionalProperties' => false,
        ],
        'ProtectedDomainRecord' => [
            'type' => 'object',
            'required' => ['id'],
            'properties' => ['id' => $opaqueId],
            'additionalProperties' => true,
        ],
        'ProtectedDomainRecordList' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ProtectedDomainRecord']],
        'AdminSearchResult' => [
            'type' => 'object',
            'required' => ['resource_type', 'resource_id', 'title', 'classification'],
            'properties' => [
                'resource_type' => ['type' => 'string'],
                'resource_id' => $opaqueId,
                'title' => ['type' => 'string'],
                'summary' => $nullableString,
                'classification' => ['type' => 'string'],
                'metadata' => ['type' => 'object', 'additionalProperties' => true],
            ],
            'additionalProperties' => false,
        ],
        'AdminSearchResults' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AdminSearchResult']],
        'AdminAdvisoryResponse' => [
            'type' => 'object',
            'required' => ['available', 'recommendation', 'reason_code', 'requires_human_decision', 'metadata'],
            'properties' => [
                'available' => ['type' => 'boolean'],
                'recommendation' => $nullableString,
                'reason_code' => ['type' => 'string'],
                'requires_human_decision' => ['type' => 'boolean'],
                'metadata' => ['type' => 'object', 'additionalProperties' => true],
            ],
            'additionalProperties' => false,
        ],
        'QueryAdminSearchInput' => [
            'type' => 'object',
            'required' => ['term'],
            'properties' => [
                'term' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 200],
                'resource_types' => ['type' => 'array', 'items' => ['type' => 'string']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
            'additionalProperties' => false,
        ],
        'RequestAdminAdvisoryInput' => [
            'type' => 'object',
            'required' => ['assistant', 'use_case', 'instruction'],
            'properties' => [
                'assistant' => ['type' => 'string'],
                'use_case' => ['type' => 'string'],
                'instruction' => ['type' => 'string', 'maxLength' => 4000],
                'context' => ['type' => 'object', 'additionalProperties' => true],
            ],
            'additionalProperties' => false,
        ],
        'TransitionWithReasonInput' => [
            'type' => 'object',
            'required' => ['status'],
            'properties' => [
                'status' => ['type' => 'string'],
                'reason_code' => ['type' => ['string', 'null'], 'maxLength' => 100],
            ],
            'additionalProperties' => false,
        ],
        'TransitionStatusInput' => [
            'type' => 'object',
            'required' => ['status'],
            'properties' => ['status' => ['type' => 'string']],
            'additionalProperties' => false,
        ],
        'ReasonCodeInput' => [
            'type' => 'object',
            'required' => ['reason_code'],
            'properties' => ['reason_code' => ['type' => 'string', 'maxLength' => 100]],
            'additionalProperties' => false,
        ],
        'IdempotencyKeyInput' => [
            'type' => 'object',
            'properties' => ['idempotency_key' => ['type' => 'string']],
            'additionalProperties' => false,
        ],
        'CreateAdminChurchInput' => [
            'type' => 'object',
            'required' => ['name', 'location_id', 'administrative_unit_id'],
            'properties' => [
                'name' => ['type' => 'string', 'maxLength' => 191],
                'location_id' => $opaqueId,
                'administrative_unit_id' => $opaqueId,
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminHomeChurchApplicationInput' => [
            'type' => 'object',
            'required' => [
                'applicant_person_id', 'church_id', 'location_id', 'administrative_unit_id',
                'proposed_name', 'expected_participants', 'meeting_day', 'meeting_time',
                'contact_email', 'contact_phone', 'guidelines_agreed_at',
            ],
            'properties' => [
                'applicant_person_id' => $opaqueId,
                'church_id' => $opaqueId,
                'location_id' => $opaqueId,
                'administrative_unit_id' => $opaqueId,
                'proposed_name' => ['type' => 'string', 'maxLength' => 191],
                'expected_participants' => ['type' => 'integer', 'minimum' => 1],
                'meeting_day' => ['type' => 'string'],
                'meeting_time' => ['type' => 'string'],
                'contact_email' => ['type' => 'string'],
                'contact_phone' => ['type' => 'string'],
                'guidelines_agreed_at' => ['type' => 'string', 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminFirstTimerInput' => [
            'type' => 'object',
            'required' => ['person_id', 'church_id'],
            'properties' => [
                'person_id' => $opaqueId,
                'church_id' => $opaqueId,
                'home_church_id' => ['type' => ['string', 'null']],
                'assigned_follow_up_person_id' => ['type' => ['string', 'null']],
                'registered_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'StartAdminChurchMembershipInput' => [
            'type' => 'object',
            'required' => ['person_id', 'church_id'],
            'properties' => [
                'person_id' => $opaqueId,
                'church_id' => $opaqueId,
                'home_church_id' => ['type' => ['string', 'null']],
                'joined_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'CaptureAdminMissionSoulInput' => [
            'type' => 'object',
            'properties' => [
                'person_id' => ['type' => ['string', 'null']],
                'given_name' => ['type' => ['string', 'null']],
                'family_name' => ['type' => ['string', 'null']],
                'middle_name' => ['type' => ['string', 'null']],
                'preferred_name' => ['type' => ['string', 'null']],
            ],
            'additionalProperties' => false,
        ],
        'AssignAdminMissionSoulMentorInput' => [
            'type' => 'object',
            'required' => ['mission_team_assignment_id'],
            'properties' => ['mission_team_assignment_id' => $opaqueId],
            'additionalProperties' => false,
        ],
        'RecordAdminMissionSoulFollowUpInput' => [
            'type' => 'object',
            'required' => ['mentor_assignment_id', 'channel_code', 'outcome_code', 'occurred_at'],
            'properties' => [
                'mentor_assignment_id' => $opaqueId,
                'channel_code' => ['type' => 'string'],
                'outcome_code' => ['type' => 'string'],
                'occurred_at' => ['type' => 'string', 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminMissionInvitationInput' => [
            'type' => 'object',
            'required' => ['crusade_id', 'requester_person_id', 'requested_location_id'],
            'properties' => [
                'crusade_id' => $opaqueId,
                'requester_person_id' => $opaqueId,
                'requested_location_id' => $opaqueId,
            ],
            'additionalProperties' => false,
        ],
        'EnrollAdminKcaStudentInput' => [
            'type' => 'object',
            'required' => ['cohort_id', 'registration_number', 'starts_on'],
            'properties' => [
                'cohort_id' => $opaqueId,
                'registration_number' => ['type' => 'string', 'maxLength' => 100],
                'starts_on' => ['type' => 'string', 'format' => 'date'],
            ],
            'additionalProperties' => false,
        ],
        'SubmitAdminKcaEvidenceInput' => [
            'type' => 'object',
            'required' => ['enrollment_id', 'file_asset_id', 'submitted_by_person_id'],
            'properties' => [
                'enrollment_id' => $opaqueId,
                'file_asset_id' => $opaqueId,
                'submitted_by_person_id' => $opaqueId,
            ],
            'additionalProperties' => false,
        ],
        'ReviewAdminKcaEvidenceInput' => [
            'type' => 'object',
            'required' => ['reviewer_person_id', 'outcome'],
            'properties' => [
                'reviewer_person_id' => $opaqueId,
                'outcome' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ],
        'IssueAdminKcaCertificateInput' => [
            'type' => 'object',
            'required' => ['certificate_number', 'completion_on', 'verification_code'],
            'properties' => [
                'certificate_number' => ['type' => 'string'],
                'completion_on' => ['type' => 'string', 'format' => 'date'],
                'verification_code' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminKcaYearInput' => [
            'type' => 'object',
            'required' => ['code', 'name', 'starts_on', 'ends_on'],
            'properties' => [
                'code' => ['type' => 'string', 'maxLength' => 50],
                'name' => ['type' => 'string', 'maxLength' => 150],
                'starts_on' => ['type' => 'string', 'format' => 'date'],
                'ends_on' => ['type' => 'string', 'format' => 'date'],
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminKcaCohortInput' => [
            'type' => 'object',
            'required' => ['code', 'name', 'starts_on', 'ends_on'],
            'properties' => [
                'code' => ['type' => 'string', 'maxLength' => 50],
                'name' => ['type' => 'string', 'maxLength' => 150],
                'starts_on' => ['type' => 'string', 'format' => 'date'],
                'ends_on' => ['type' => 'string', 'format' => 'date'],
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminKcaModuleInput' => [
            'type' => 'object',
            'required' => ['code', 'title', 'sequence'],
            'properties' => [
                'code' => ['type' => 'string', 'maxLength' => 50],
                'title' => ['type' => 'string', 'maxLength' => 191],
                'sequence' => ['type' => 'integer', 'minimum' => 1],
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminKcaLessonInput' => [
            'type' => 'object',
            'required' => ['code', 'title', 'sequence'],
            'properties' => [
                'code' => ['type' => 'string', 'maxLength' => 50],
                'title' => ['type' => 'string', 'maxLength' => 191],
                'sequence' => ['type' => 'integer', 'minimum' => 1],
            ],
            'additionalProperties' => false,
        ],
        'RecordAdminKcaAttendanceInput' => [
            'type' => 'object',
            'required' => ['lesson_id', 'status', 'session_on'],
            'properties' => [
                'lesson_id' => $opaqueId,
                'status' => ['type' => 'string'],
                'session_on' => ['type' => 'string', 'format' => 'date'],
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminPressPublicationInput' => [
            'type' => 'object',
            'required' => ['title', 'publisher_name', 'language_code', 'format'],
            'properties' => [
                'title' => ['type' => 'string'],
                'publisher_name' => ['type' => 'string'],
                'language_code' => ['type' => 'string'],
                'format' => ['type' => 'string'],
                'subtitle' => $nullableString,
                'edition' => $nullableString,
                'publication_date' => ['type' => ['string', 'null'], 'format' => 'date'],
                'copyright_year' => ['type' => ['integer', 'null']],
                'page_count' => ['type' => ['integer', 'null']],
                'category' => $nullableString,
                'description' => $nullableString,
                'cover_file_asset_id' => ['type' => ['string', 'null']],
                'content_file_asset_id' => ['type' => ['string', 'null']],
                'price_minor' => ['type' => ['integer', 'null']],
                'currency_code' => $nullableString,
            ],
            'additionalProperties' => false,
        ],
        'AssignAdminPressPublicationIsbnInput' => [
            'type' => 'object',
            'required' => ['isbn', 'reason_code'],
            'properties' => [
                'isbn' => ['type' => 'string'],
                'reason_code' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ],
        'AddAdminPressPublicationContributorInput' => [
            'type' => 'object',
            'required' => ['person_id', 'role'],
            'properties' => [
                'person_id' => $opaqueId,
                'role' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminPressTranslationInput' => [
            'type' => 'object',
            'required' => ['target_language_code', 'translated_title'],
            'properties' => [
                'target_language_code' => ['type' => 'string'],
                'translated_title' => ['type' => 'string'],
                'translated_subtitle' => $nullableString,
                'translated_description' => $nullableString,
                'translated_content' => $nullableString,
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminMinistryEventInput' => [
            'type' => 'object',
            'required' => ['category_code', 'name', 'starts_at', 'ends_at'],
            'properties' => [
                'location_id' => ['type' => ['string', 'null']],
                'category_code' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'starts_at' => ['type' => 'string', 'format' => 'date-time'],
                'ends_at' => ['type' => 'string', 'format' => 'date-time'],
                'registration_opens_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                'registration_closes_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                'fee_amount_minor' => ['type' => ['integer', 'null']],
                'fee_currency' => $nullableString,
                'capacity' => ['type' => ['integer', 'null']],
                'published_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'RegisterAdminEventRegistrationInput' => [
            'type' => 'object',
            'required' => ['person_id'],
            'properties' => ['person_id' => $opaqueId],
            'additionalProperties' => false,
        ],
        'RecordAdminEventAttendanceInput' => [
            'type' => 'object',
            'required' => ['source_code'],
            'properties' => ['source_code' => ['type' => 'string']],
            'additionalProperties' => false,
        ],
        'RecordAdminEventFeedbackInput' => [
            'type' => 'object',
            'required' => ['rating'],
            'properties' => ['rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5]],
            'additionalProperties' => false,
        ],
        'CreateAdminPaymentIntentInput' => [
            'type' => 'object',
            'required' => ['event_registration_id'],
            'properties' => ['event_registration_id' => $opaqueId],
            'additionalProperties' => false,
        ],
        'RequestAdminPaymentRefundInput' => [
            'type' => 'object',
            'required' => ['amount_minor', 'reason_code'],
            'properties' => [
                'amount_minor' => ['type' => 'integer', 'minimum' => 1],
                'reason_code' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminCommunicationTemplateInput' => [
            'type' => 'object',
            'required' => ['code', 'channel', 'locale', 'subject', 'body'],
            'properties' => [
                'code' => ['type' => 'string'],
                'channel' => ['type' => 'string'],
                'locale' => ['type' => 'string'],
                'subject' => ['type' => 'string'],
                'body' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminCommunicationAudienceInput' => [
            'type' => 'object',
            'required' => ['code', 'name', 'rules'],
            'properties' => [
                'code' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'rules' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            ],
            'additionalProperties' => false,
        ],
        'PrepareAdminCommunicationBroadcastInput' => [
            'type' => 'object',
            'required' => ['template_id', 'audience_id', 'kind', 'channel', 'purpose'],
            'properties' => [
                'template_id' => $opaqueId,
                'audience_id' => $opaqueId,
                'kind' => ['type' => 'string'],
                'channel' => ['type' => 'string'],
                'purpose' => ['type' => 'string'],
                'scheduled_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminAlertRuleInput' => [
            'type' => 'object',
            'required' => ['code', 'title', 'condition_type', 'severity', 'configuration'],
            'properties' => [
                'code' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'condition_type' => ['type' => 'string'],
                'severity' => ['type' => 'string'],
                'configuration' => ['type' => 'object', 'additionalProperties' => true],
                'scope_type' => $nullableString,
                'scope_key' => $nullableString,
            ],
            'additionalProperties' => false,
        ],
        'SetAdminAlertRuleEnabledInput' => [
            'type' => 'object',
            'required' => ['enabled'],
            'properties' => ['enabled' => ['type' => 'boolean']],
            'additionalProperties' => false,
        ],
        'EvaluateAdminAlertRuleInput' => [
            'type' => 'object',
            'required' => ['condition_reference_type', 'condition_reference_key'],
            'properties' => [
                'condition_reference_type' => ['type' => 'string'],
                'condition_reference_key' => ['type' => 'string'],
                'summary' => $nullableString,
                'facts' => ['type' => 'object', 'additionalProperties' => true],
                'scope_type' => $nullableString,
                'scope_key' => $nullableString,
            ],
            'additionalProperties' => false,
        ],
        'SubmitAdminDataSubjectRequestInput' => [
            'type' => 'object',
            'required' => ['person_id', 'request_type'],
            'properties' => [
                'person_id' => $opaqueId,
                'request_type' => ['type' => 'string'],
                'notes' => $nullableString,
            ],
            'additionalProperties' => false,
        ],
        'BeginAdminDataExportInput' => [
            'type' => 'object',
            'required' => ['data_categories'],
            'properties' => [
                'data_categories' => ['type' => 'array', 'items' => ['type' => 'string']],
                'scope_type' => $nullableString,
                'scope_key' => $nullableString,
            ],
            'additionalProperties' => false,
        ],
        'CompleteAdminDataExportInput' => [
            'type' => 'object',
            'required' => ['file_asset_id', 'expires_at'],
            'properties' => [
                'file_asset_id' => $opaqueId,
                'expires_at' => ['type' => 'string', 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'ReportAdminSafeguardingIncidentInput' => [
            'type' => 'object',
            'required' => ['concern_type', 'severity', 'restricted_summary'],
            'properties' => [
                'concern_type' => ['type' => 'string'],
                'severity' => ['type' => 'string'],
                'restricted_summary' => ['type' => 'string'],
                'subject_person_id' => ['type' => ['string', 'null']],
                'occurred_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'RegisterAdminGuardianRelationshipInput' => [
            'type' => 'object',
            'required' => ['guardian_person_id', 'child_person_id', 'relationship_type'],
            'properties' => [
                'guardian_person_id' => $opaqueId,
                'child_person_id' => $opaqueId,
                'relationship_type' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ],
        'StoreAdminFileAssetInput' => [
            'type' => 'object',
            'required' => ['purpose', 'classification'],
            'properties' => [
                'purpose' => ['type' => 'string'],
                'classification' => ['type' => 'string'],
                'owner_person_id' => ['type' => ['string', 'null']],
            ],
            'additionalProperties' => true,
        ],
        'AssignAdminUserRoleInput' => [
            'type' => 'object',
            'required' => ['role_id'],
            'properties' => [
                'role_id' => $opaqueId,
                'expires_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'AssignAdminRoleAssignmentScopeInput' => [
            'type' => 'object',
            'required' => ['scope_type', 'scope_key'],
            'properties' => [
                'scope_type' => ['type' => 'string'],
                'scope_key' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ],
        'GrantAdminRolePermissionInput' => [
            'type' => 'object',
            'required' => ['permission_id'],
            'properties' => ['permission_id' => $opaqueId],
            'additionalProperties' => false,
        ],
        'SuspendAdminUserInput' => [
            'type' => 'object',
            'required' => ['reason'],
            'properties' => ['reason' => ['type' => 'string']],
            'additionalProperties' => false,
        ],
        'CreateAdminContentPageInput' => [
            'type' => 'object',
            'required' => ['slug', 'title', 'body'],
            'properties' => [
                'slug' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'summary' => $nullableString,
                'body' => ['type' => 'string'],
                'locale' => ['type' => 'string'],
                'published_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'UpdateAdminContentPageInput' => [
            'type' => 'object',
            'properties' => [
                'slug' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'summary' => $nullableString,
                'body' => ['type' => 'string'],
                'locale' => ['type' => 'string'],
                'published_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'CreateAdminContentPageItemInput' => [
            'type' => 'object',
            'required' => ['kind', 'title', 'body'],
            'properties' => [
                'kind' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'meta' => ['type' => 'object', 'additionalProperties' => true],
                'href' => $nullableString,
                'sort_order' => ['type' => 'integer'],
                'published_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
        'GrantUserConsentInput' => [
            'type' => 'object',
            'required' => ['purpose', 'policy_version'],
            'properties' => [
                'purpose' => ['type' => 'string'],
                'policy_version' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ],
        'UpdateUserPreferencesInput' => [
            'type' => 'object',
            'required' => ['locale', 'timezone', 'notification_channels'],
            'properties' => [
                'locale' => ['type' => 'string'],
                'timezone' => ['type' => 'string'],
                'notification_channels' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'additionalProperties' => false,
        ],
        'CreateUserGivingPaymentIntentInput' => [
            'type' => 'object',
            'required' => ['amount_minor', 'currency'],
            'properties' => [
                'amount_minor' => ['type' => 'integer', 'minimum' => 1],
                'currency' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 3],
            ],
            'additionalProperties' => false,
        ],
        'CreateUserPrayerRequestInput' => [
            'type' => 'object',
            'required' => ['subject', 'body'],
            'properties' => [
                'subject' => ['type' => 'string'],
                'body' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ],
        'CreateUserPastoralNeedInput' => [
            'type' => 'object',
            'required' => ['category', 'summary'],
            'properties' => [
                'category' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ],
        'CreateUserMessageConversationInput' => [
            'type' => 'object',
            'required' => ['participant_person_ids', 'first_message'],
            'properties' => [
                'participant_person_ids' => ['type' => 'array', 'items' => $opaqueId],
                'subject' => $nullableString,
                'first_message' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ],
        'CreateUserConversationMessageInput' => [
            'type' => 'object',
            'required' => ['body'],
            'properties' => ['body' => ['type' => 'string']],
            'additionalProperties' => false,
        ],
        'UpdateUserSyncCheckpointInput' => [
            'type' => 'object',
            'required' => ['cursor'],
            'properties' => ['cursor' => ['type' => 'string']],
            'additionalProperties' => false,
        ],
        'RegisterUserEventRegistrationInput' => [
            'type' => 'object',
            'properties' => ['idempotency_key' => ['type' => 'string']],
            'additionalProperties' => false,
        ],
        'RecordUserEventFeedbackInput' => [
            'type' => 'object',
            'required' => ['rating', 'registration_id'],
            'properties' => [
                'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                'registration_id' => $opaqueId,
            ],
            'additionalProperties' => false,
        ],
        'SubmitUserDataSubjectRequestInput' => [
            'type' => 'object',
            'required' => ['request_type'],
            'properties' => [
                'request_type' => ['type' => 'string'],
                'notes' => $nullableString,
            ],
            'additionalProperties' => false,
        ],
        'StoreUserFileAssetInput' => [
            'type' => 'object',
            'required' => ['purpose', 'classification'],
            'properties' => [
                'purpose' => ['type' => 'string'],
                'classification' => ['type' => 'string'],
            ],
            'additionalProperties' => true,
        ],
        'StartUserChurchMembershipInput' => [
            'type' => 'object',
            'properties' => [
                'home_church_id' => ['type' => ['string', 'null']],
            ],
            'additionalProperties' => false,
        ],
        'SubmitUserHomeChurchReportInput' => [
            'type' => 'object',
            'required' => ['summary'],
            'properties' => [
                'summary' => ['type' => 'string', 'maxLength' => 2000],
                'period_code' => ['type' => ['string', 'null']],
            ],
            'additionalProperties' => false,
        ],
        'UserHomeChurchReportSubmission' => [
            'type' => 'object',
            'required' => ['id', 'status', 'submitted_at'],
            'properties' => [
                'id' => $opaqueId,
                'status' => ['type' => 'string', 'const' => 'submitted'],
                'submitted_at' => ['type' => 'string', 'format' => 'date-time'],
            ],
            'additionalProperties' => false,
        ],
    ];
}

/** @return array<int, array<string, array<int, string>>> */
function operationSecurity(string $operationId, string $path, string $method): array
{
    $anonymous = [
        'getBrowserCsrfCookie',
        'verifyEmail',
        'mobileLogin',
        'mobileRefresh',
    ];

    if (in_array($operationId, $anonymous, true)) {
        return [];
    }

    if (str_starts_with($path, '/api/v1/mobile/')) {
        return [['bearerAuth' => []]];
    }

    if (str_starts_with($path, '/api/v1/auth/')) {
        return $method === 'GET' ? [['cookieSession' => []]] : [['cookieSession' => [], 'csrfToken' => []]];
    }

    return [
        ['cookieSession' => [], 'csrfToken' => []],
        ['bearerAuth' => []],
    ];
}

function operationIsFileStream(string $operationId): bool
{
    return in_array($operationId, [
        'streamUserFileAsset',
        'streamAdminFileAssetContent',
    ], true);
}

function operationHasRequestBody(string $operationId, string $method): bool
{
    if (! in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        return false;
    }

    return ! in_array($operationId, [
        'browserLogout',
        'mobileLogout',
        'sendEmailVerification',
        'enableAdminFeatureFlag',
        'activateAdminObjectStorage',
        'validateAdminObjectStorage',
        'markUserNotificationRead',
        'approveAdminFileAsset',
        'completeUserGivingPaymentIntent',
    ], true);
}

function operationSuccessStatus(string $operationId): bool
{
    if (in_array($operationId, [
        'registerBrowserUser',
        'browserMfaSetup',
        'mobileMfaSetup',
        'grantUserConsent',
        'grantAdminRolePermission',
        'assignAdminUserRole',
        'assignAdminRoleAssignmentScope',
        'assignAdminMissionSoulMentor',
        'startAdminChurchMembership',
        'startUserChurchMembership',
        'prepareAdminCommunicationBroadcast',
        'addAdminPressPublicationContributor',
        'requestAdminPaymentRefund',
        'attemptAdminCommunicationDelivery',
    ], true)) {
        return true;
    }

    foreach ([
        'create',
        'store',
        'register',
        'capture',
        'enroll',
        'issue',
        'submit',
        'record',
        'report',
    ] as $verb) {
        if (str_starts_with($operationId, $verb)) {
            return true;
        }
    }

    return false;
}

function operationTag(string $path): string
{
    return match (true) {
        str_contains($path, '/admin/') => 'Admin',
        str_contains($path, '/mobile/') => 'Mobile identity',
        str_contains($path, '/user/') => 'User',
        default => 'Browser identity',
    };
}

/**
 * @param  array<string, array{method: string, path: string}>  $operations
 * @param  array<string, array{request?: string, response?: string}>  $operationSchemas
 */
function renderTypeScript(string $digest, array $operations, array $operationSchemas): string
{
    $methods = [];

    foreach ($operations as $operationId => $operation) {
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $operation['path'], $matches);
        $parameters = array_map(fn (string $name): string => "{$name}: string", $matches[1]);
        $hasBody = operationHasRequestBody($operationId, $operation['method']);
        if ($hasBody) {
            $bodyType = $operationSchemas[$operationId]['request'] ?? 'JsonObject';
            $parameters[] = isset($operationSchemas[$operationId]['request'])
                ? "body: {$bodyType}"
                : "body: {$bodyType} = {}";
        }
        $parameters[] = 'options: ProtectedRequestOptions = {}';
        $path = $operation['path'];
        foreach ($matches[1] as $name) {
            $path = str_replace('{'.$name.'}', '${encodeURIComponent('.$name.')}', $path);
        }
        $pathExpression = $matches[1] === [] ? "'{$path}'" : "`{$path}`";
        $body = $hasBody ? ', body as unknown as JsonObject' : '';
        $responseType = $operationSchemas[$operationId]['response'] ?? 'JsonValue';
        $methods[] = "  public {$operationId}(".implode(', ', $parameters)."): Promise<SuccessEnvelope<{$responseType}>> {\n"
            ."    return this.request<{$responseType}>('{$operation['method']}', {$pathExpression}, options{$body});\n  }";
    }

    $renderedMethods = implode("\n\n", $methods);
    $domainTypes = renderTypeScriptDomainTypes();

    return <<<TYPESCRIPT
// Generated from openapi/protected-v1.openapi.json (SHA-256: {$digest}).
// Do not edit directly. Run: php scripts/generate-protected-api.php

export type JsonPrimitive = string | number | boolean | null;
export type JsonValue = JsonPrimitive | JsonObject | JsonValue[];
export interface JsonObject { [key: string]: JsonValue; }
export interface SuccessEnvelope<T> { data: T; meta: JsonObject; correlation_id: string; }
export interface ErrorEnvelope { error: { code: string; message: string; details: JsonObject }; meta: JsonObject; correlation_id: string; }
export interface ProtectedRequestOptions {
  correlationId?: string;
  query?: Record<string, string | number | boolean | undefined>;
  csrfToken?: string;
  bearerToken?: string;
  deviceIdentifier?: string;
  scope?: { type: string; id: string };
  signal?: AbortSignal;
}

{$domainTypes}

export class ProtectedApiError extends Error {
  public constructor(public readonly status: number, public readonly envelope: ErrorEnvelope) {
    super(envelope.error.message);
    this.name = 'ProtectedApiError';
  }
}

export class FamilyHouseProtectedApiClient {
  public constructor(private readonly baseUrl: string, private readonly fetcher: typeof fetch = globalThis.fetch) {}

{$renderedMethods}

  private async request<T>(
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
    path: string,
    options: ProtectedRequestOptions,
    body?: JsonObject,
  ): Promise<SuccessEnvelope<T>> {
    const headers: Record<string, string> = { Accept: 'application/json' };
    const url = new URL(path, this.baseUrl);
    for (const [key, value] of Object.entries(options.query ?? {})) {
      if (value !== undefined) url.searchParams.append(key, String(value));
    }
    if (options.correlationId) headers['X-Correlation-ID'] = options.correlationId;
    if (options.csrfToken) {
      headers['X-CSRF-TOKEN'] = options.csrfToken;
      headers['X-XSRF-TOKEN'] = options.csrfToken;
    }
    if (options.bearerToken) headers.Authorization = 'Bearer ' + options.bearerToken;
    if (options.deviceIdentifier) headers['X-Device-Identifier'] = options.deviceIdentifier;
    if (options.scope) {
      headers['X-Scope-Type'] = options.scope.type;
      headers['X-Scope-ID'] = options.scope.id;
    }
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    const response = await this.fetcher(url, {
      method,
      headers,
      credentials: 'include',
      body: body === undefined ? undefined : JSON.stringify(body),
      signal: options.signal,
    });
    const payload = (await response.json()) as SuccessEnvelope<JsonValue> | ErrorEnvelope;
    if (!response.ok) throw new ProtectedApiError(response.status, payload as ErrorEnvelope);
    return payload as SuccessEnvelope<T>;
  }
}
TYPESCRIPT;
}

function renderTypeScriptDomainTypes(): string
{
    return <<<'TYPESCRIPT'
export interface AdminScope { type: string; id: string; }
export interface AdminCountry { id: string; iso_code: string; name: string; created_at: string | null; }
export type AdminCountryList = AdminCountry[];
export interface CreateAdminCountryInput { iso_code: string; name: string; }
export interface AdminAdministrativeLevel { id: string; country_id: string; code: string; name: string; sort_order: number; }
export type AdminAdministrativeLevelList = AdminAdministrativeLevel[];
export interface CreateAdminAdministrativeLevelInput { code: string; name: string; sort_order: number; }
export interface AdminAdministrativeUnit {
  id: string; name: string; reference_code: string | null; country: JsonObject;
  administrative_level: JsonObject; parent: JsonObject | null; created_at: string | null;
}
export type AdminAdministrativeUnitList = AdminAdministrativeUnit[];
export interface CreateAdminAdministrativeUnitInput {
  country_id: string; administrative_level_id: string; name: string; parent_id?: string | null; reference_code?: string | null;
}
export interface MoveAdminAdministrativeUnitInput { parent_id: string | null; }
export interface AdminLocation {
  id: string; name: string; country: JsonObject; administrative_unit: JsonObject | null;
  address: JsonObject; timezone: string; coordinates: JsonObject | null; created_at: string | null;
}
export type AdminLocationList = AdminLocation[];
export interface CreateAdminLocationInput {
  country_id: string; name: string; timezone: string; administrative_unit_id?: string | null;
  address_line_one?: string | null; address_line_two?: string | null; locality?: string | null;
  postal_code?: string | null; latitude?: number | null; longitude?: number | null;
}
export interface AdminPlatformConfiguration {
  id: string; key: string; value_type: 'string' | 'integer' | 'boolean' | 'json';
  classification: 'internal' | 'confidential'; environment: string; scope: AdminScope | null;
  value: JsonValue; has_value: boolean; updated_at: string | null;
}
export type AdminPlatformConfigurationList = AdminPlatformConfiguration[];
export interface UpsertAdminPlatformConfigurationInput {
  key: string; value_type: 'string' | 'integer' | 'boolean' | 'json';
  classification: 'internal' | 'confidential'; value: JsonValue; environment: string;
  scope_type?: string | null; scope_id?: string | null;
}
export interface AdminFeatureFlag {
  id: string; key: string; environment: string; scope: AdminScope | null; enabled: boolean;
  rollout_percentage: number; starts_at: string | null; ends_at: string | null; updated_at: string | null;
}
export type AdminFeatureFlagList = AdminFeatureFlag[];
export interface UpsertAdminFeatureFlagInput {
  key: string; environment: string; rollout_percentage: number; scope_type?: string | null;
  scope_id?: string | null; starts_at?: string | null; ends_at?: string | null;
}
export interface AdminObjectStorageStatus {
  provider: 's3'; configured: boolean; active: boolean; credentials_configured: boolean;
  active_provider?: 'local' | 's3'; region?: string | null; bucket?: string | null;
  endpoint?: string | null; url?: string | null; root_prefix?: string | null;
  use_path_style_endpoint?: boolean; configuration_revision?: number;
  validation?: JsonObject; validation_result?: JsonObject; activated_at?: string | null;
}
export interface ConfigureAdminObjectStorageInput {
  access_key_id: string; secret_access_key: string; region: string; bucket: string;
  endpoint?: string | null; url?: string | null; root_prefix?: string | null;
  use_path_style_endpoint?: boolean;
}
export interface AdminObjectStorageDeactivation { active_provider: 'local'; object_storage_active: false; }
export interface AdminMapsProviderStatus {
  configured: boolean; active: boolean; active_provider: 'google' | 'mapbox' | 'leaflet';
  providers: JsonObject; default_center?: { latitude: number; longitude: number };
  default_zoom?: number; configuration_revision?: number; validation?: JsonObject;
  activated_at?: string | null;
}
export interface ConfigureAdminMapsProviderInput {
  active_provider: 'google' | 'mapbox' | 'leaflet';
  google_api_key?: string | null; mapbox_access_token?: string | null;
  leaflet_tile_url?: string | null; default_latitude?: number | null;
  default_longitude?: number | null; default_zoom?: number | null;
}
export interface AdminMapsProviderDeactivation {
  active: false; active_provider: 'google' | 'mapbox' | 'leaflet';
}
export interface UserCapabilities {
  permissions: string[];
  scopes: Array<{ type: string; key: string }>;
}
export interface CheckUserAuthorizationInput {
  permission: string; scope_type?: string | null; scope_id?: string | null; resource_id?: string | null;
}
export interface UserAuthorizationDecision {
  allowed: boolean; state: 'allowed' | 'forbidden'; permission: string;
  canonical_permission: string; reason: string;
  scope?: { type: string; id: string }; decision_id?: string | null;
}
export interface ProtectedDomainRecord { id: string; [key: string]: JsonValue; }
export type ProtectedDomainRecordList = ProtectedDomainRecord[];
export interface AdminSearchResult {
  resource_type: string; resource_id: string; title: string;
  summary?: string | null; classification: string; metadata?: JsonObject;
}
export type AdminSearchResults = AdminSearchResult[];
export interface AdminAdvisoryResponse {
  available: boolean; recommendation: string | null; reason_code: string;
  requires_human_decision: boolean; metadata: JsonObject;
}
export interface QueryAdminSearchInput { term: string; resource_types?: string[]; limit?: number; }
export interface RequestAdminAdvisoryInput {
  assistant: string; use_case: string; instruction: string; context?: JsonObject;
}
export interface TransitionWithReasonInput { status: string; reason_code?: string | null; }
export interface TransitionStatusInput { status: string; }
export interface ReasonCodeInput { reason_code: string; }
export interface IdempotencyKeyInput { idempotency_key?: string; }
export interface CreateAdminChurchInput { name: string; location_id: string; administrative_unit_id: string; }
export interface CreateAdminHomeChurchApplicationInput {
  applicant_person_id: string; church_id: string; location_id: string; administrative_unit_id: string;
  proposed_name: string; expected_participants: number; meeting_day: string; meeting_time: string;
  contact_email: string; contact_phone: string; guidelines_agreed_at: string;
}
export interface CreateAdminFirstTimerInput {
  person_id: string; church_id: string; home_church_id?: string | null;
  assigned_follow_up_person_id?: string | null; registered_at?: string | null;
}
export interface StartAdminChurchMembershipInput {
  person_id: string; church_id: string; home_church_id?: string | null; joined_at?: string | null;
}
export interface CaptureAdminMissionSoulInput {
  person_id?: string | null; given_name?: string | null; family_name?: string | null;
  middle_name?: string | null; preferred_name?: string | null;
}
export interface AssignAdminMissionSoulMentorInput { mission_team_assignment_id: string; }
export interface RecordAdminMissionSoulFollowUpInput {
  mentor_assignment_id: string; channel_code: string; outcome_code: string; occurred_at: string;
}
export interface CreateAdminMissionInvitationInput {
  crusade_id: string; requester_person_id: string; requested_location_id: string;
}
export interface EnrollAdminKcaStudentInput { cohort_id: string; registration_number: string; starts_on: string; }
export interface SubmitAdminKcaEvidenceInput {
  enrollment_id: string; file_asset_id: string; submitted_by_person_id: string;
}
export interface ReviewAdminKcaEvidenceInput { reviewer_person_id: string; outcome: string; }
export interface IssueAdminKcaCertificateInput {
  certificate_number: string; completion_on: string; verification_code: string;
}
export interface CreateAdminKcaYearInput { code: string; name: string; starts_on: string; ends_on: string; }
export interface CreateAdminKcaCohortInput { code: string; name: string; starts_on: string; ends_on: string; }
export interface CreateAdminKcaModuleInput { code: string; title: string; sequence: number; }
export interface CreateAdminKcaLessonInput { code: string; title: string; sequence: number; }
export interface RecordAdminKcaAttendanceInput { lesson_id: string; status: string; session_on: string; }
export interface CreateAdminPressPublicationInput {
  title: string; publisher_name: string; language_code: string; format: string;
  subtitle?: string | null; edition?: string | null; publication_date?: string | null;
  copyright_year?: number | null; page_count?: number | null; category?: string | null;
  description?: string | null; cover_file_asset_id?: string | null; content_file_asset_id?: string | null;
  price_minor?: number | null; currency_code?: string | null;
}
export interface AssignAdminPressPublicationIsbnInput { isbn: string; reason_code: string; }
export interface AddAdminPressPublicationContributorInput { person_id: string; role: string; }
export interface CreateAdminPressTranslationInput {
  target_language_code: string; translated_title: string;
  translated_subtitle?: string | null; translated_description?: string | null; translated_content?: string | null;
}
export interface CreateAdminMinistryEventInput {
  category_code: string; name: string; starts_at: string; ends_at: string;
  location_id?: string | null; registration_opens_at?: string | null; registration_closes_at?: string | null;
  fee_amount_minor?: number | null; fee_currency?: string | null; capacity?: number | null; published_at?: string | null;
}
export interface RegisterAdminEventRegistrationInput { person_id: string; }
export interface RecordAdminEventAttendanceInput { source_code: string; }
export interface RecordAdminEventFeedbackInput { rating: number; }
export interface CreateAdminPaymentIntentInput { event_registration_id: string; }
export interface RequestAdminPaymentRefundInput { amount_minor: number; reason_code: string; }
export interface CreateAdminCommunicationTemplateInput {
  code: string; channel: string; locale: string; subject: string; body: string;
}
export interface CreateAdminCommunicationAudienceInput {
  code: string; name: string; rules: JsonObject[];
}
export interface PrepareAdminCommunicationBroadcastInput {
  template_id: string; audience_id: string; kind: string; channel: string; purpose: string; scheduled_at?: string | null;
}
export interface CreateAdminAlertRuleInput {
  code: string; title: string; condition_type: string; severity: string; configuration: JsonObject;
  scope_type?: string | null; scope_key?: string | null;
}
export interface SetAdminAlertRuleEnabledInput { enabled: boolean; }
export interface EvaluateAdminAlertRuleInput {
  condition_reference_type: string; condition_reference_key: string; summary?: string | null;
  facts?: JsonObject; scope_type?: string | null; scope_key?: string | null;
}
export interface SubmitAdminDataSubjectRequestInput { person_id: string; request_type: string; notes?: string | null; }
export interface BeginAdminDataExportInput {
  data_categories: string[]; scope_type?: string | null; scope_key?: string | null;
}
export interface CompleteAdminDataExportInput { file_asset_id: string; expires_at: string; }
export interface ReportAdminSafeguardingIncidentInput {
  concern_type: string; severity: string; restricted_summary: string;
  subject_person_id?: string | null; occurred_at?: string | null;
}
export interface RegisterAdminGuardianRelationshipInput {
  guardian_person_id: string; child_person_id: string; relationship_type: string;
}
export interface StoreAdminFileAssetInput { purpose: string; classification: string; owner_person_id?: string | null; [key: string]: JsonValue | undefined; }
export interface AssignAdminUserRoleInput { role_id: string; expires_at?: string | null; }
export interface AssignAdminRoleAssignmentScopeInput { scope_type: string; scope_key: string; }
export interface GrantAdminRolePermissionInput { permission_id: string; }
export interface SuspendAdminUserInput { reason: string; }
export interface CreateAdminContentPageInput {
  slug: string; title: string; body: string; summary?: string | null; locale?: string; published_at?: string | null;
}
export interface UpdateAdminContentPageInput {
  slug?: string; title?: string; body?: string; summary?: string | null; locale?: string; published_at?: string | null;
}
export interface CreateAdminContentPageItemInput {
  kind: string; title: string; body: string; meta?: JsonObject; href?: string | null;
  sort_order?: number; published_at?: string | null;
}
export interface GrantUserConsentInput { purpose: string; policy_version: string; }
export interface UpdateUserPreferencesInput { locale: string; timezone: string; notification_channels: string[]; }
export interface CreateUserGivingPaymentIntentInput { amount_minor: number; currency: string; }
export interface CreateUserPrayerRequestInput { subject: string; body: string; }
export interface CreateUserPastoralNeedInput { category: string; summary: string; }
export interface CreateUserMessageConversationInput {
  participant_person_ids: string[]; first_message: string; subject?: string | null;
}
export interface CreateUserConversationMessageInput { body: string; }
export interface UpdateUserSyncCheckpointInput { cursor: string; }
export interface RegisterUserEventRegistrationInput { idempotency_key?: string; }
export interface RecordUserEventFeedbackInput { rating: number; registration_id: string; }
export interface SubmitUserDataSubjectRequestInput { request_type: string; notes?: string | null; }
export interface StoreUserFileAssetInput { purpose: string; classification: string; [key: string]: JsonValue; }
export interface StartUserChurchMembershipInput { home_church_id?: string | null; }
export interface SubmitUserHomeChurchReportInput { summary: string; period_code?: string | null; }
export interface UserHomeChurchReportSubmission { id: string; status: 'submitted'; submitted_at: string; }
TYPESCRIPT;
}

/**
 * @param  array<string, array{method: string, path: string}>  $operations
 */
function renderDart(string $digest, array $operations): string
{
    $methods = [];

    foreach ($operations as $operationId => $operation) {
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $operation['path'], $matches);
        $named = array_map(fn (string $name): string => "required String {$name}", $matches[1]);
        if (operationHasRequestBody($operationId, $operation['method'])) {
            $named[] = 'JsonMap body = const {}';
        }
        $named[] = 'ProtectedRequestOptions options = const ProtectedRequestOptions()';
        $path = $operation['path'];
        foreach ($matches[1] as $name) {
            $path = str_replace('{'.$name.'}', '${Uri.encodeComponent('.$name.')}', $path);
        }
        $body = operationHasRequestBody($operationId, $operation['method']) ? ', body: body' : '';
        $methods[] = "  Future<JsonMap> {$operationId}({".implode(', ', $named)."}) =>\n"
            ."      _request('{$operation['method']}', '{$path}', options{$body});";
    }

    $renderedMethods = implode("\n\n", $methods);

    return <<<DART
// Generated from openapi/protected-v1.openapi.json (SHA-256: {$digest}).
// Do not edit directly. Run: php scripts/generate-protected-api.php

typedef JsonMap = Map<String, Object?>;

abstract interface class ProtectedApiTransport {
  Future<ProtectedApiTransportResponse> send(
    String method,
    Uri uri, {
    Map<String, String> headers = const {},
    JsonMap? body,
    bool includeCredentials = true,
  });
}

final class ProtectedApiTransportResponse {
  const ProtectedApiTransportResponse({required this.statusCode, required this.body});
  final int statusCode;
  final JsonMap body;
}

final class ProtectedRequestOptions {
  const ProtectedRequestOptions({
    this.correlationId,
    this.query = const {},
    this.csrfToken,
    this.bearerToken,
    this.deviceIdentifier,
    this.scopeType,
    this.scopeId,
  });
  final String? correlationId;
  final JsonMap query;
  final String? csrfToken;
  final String? bearerToken;
  final String? deviceIdentifier;
  final String? scopeType;
  final String? scopeId;
}

final class ProtectedApiException implements Exception {
  const ProtectedApiException(this.statusCode, this.payload);
  final int statusCode;
  final JsonMap payload;
}

final class FamilyHouseProtectedApiClient {
  const FamilyHouseProtectedApiClient({required this.baseUri, required this.transport});
  final Uri baseUri;
  final ProtectedApiTransport transport;

{$renderedMethods}

  Future<JsonMap> _request(
    String method,
    String path,
    ProtectedRequestOptions options, {
    JsonMap? body,
  }) async {
    var uri = baseUri.resolve(path);
    if (options.query.isNotEmpty) {
      uri = uri.replace(queryParameters: options.query.map((key, value) => MapEntry(key, '\$value')));
    }
    final headers = <String, String>{'Accept': 'application/json'};
    if (options.correlationId != null) headers['X-Correlation-ID'] = options.correlationId!;
    if (options.csrfToken != null) headers['X-XSRF-TOKEN'] = options.csrfToken!;
    if (options.bearerToken != null) headers['Authorization'] = 'Bearer ' + options.bearerToken!;
    if (options.deviceIdentifier != null) headers['X-Device-Identifier'] = options.deviceIdentifier!;
    if (options.scopeType != null && options.scopeId != null) {
      headers['X-Scope-Type'] = options.scopeType!;
      headers['X-Scope-ID'] = options.scopeId!;
    }
    final response = await transport.send(method, uri, headers: headers, body: body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ProtectedApiException(response.statusCode, response.body);
    }
    return response.body;
  }
}
DART;
}
