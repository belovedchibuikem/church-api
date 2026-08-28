<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ConfigureMapsProviderRequest;
use App\Http\Requests\Api\V1\Admin\MapsProviderActionRequest;
use App\Http\Resources\Api\V1\Admin\MapsProviderConfigurationResource;
use App\Maps\Actions\ActivateMapsProviderAction;
use App\Maps\Actions\ConfigureMapsProviderAction;
use App\Maps\Actions\DeactivateMapsProviderAction;
use App\Models\MapsProviderConfiguration;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class MapsProviderController extends Controller
{
    public function show(
        MapsProviderActionRequest $request,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $configuration = MapsProviderConfiguration::query()->first();

        if ($configuration === null) {
            return ApiResponse::success($request, [
                'configured' => false,
                'active' => false,
                'active_provider' => 'leaflet',
                'providers' => [
                    'google' => ['label' => 'Google Maps', 'requires_key' => true, 'key_configured' => false],
                    'mapbox' => ['label' => 'Mapbox', 'requires_key' => true, 'key_configured' => false],
                    'leaflet' => [
                        'label' => 'Leaflet (OpenStreetMap)',
                        'requires_key' => false,
                        'key_configured' => true,
                        'tile_url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                    ],
                ],
                'default_center' => ['latitude' => 6.5244, 'longitude' => 3.3792],
                'default_zoom' => 12,
            ]);
        }

        return ApiResponse::success(
            $request,
            (new MapsProviderConfigurationResource($configuration))->resolve($request),
        );
    }

    public function configure(
        ConfigureMapsProviderRequest $request,
        ConfigureMapsProviderAction $configure,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $configuration = $configure->handle($request->validated(), $context->actor($request));

        return ApiResponse::success(
            $request,
            (new MapsProviderConfigurationResource($configuration))->resolve($request),
        );
    }

    public function activate(
        MapsProviderActionRequest $request,
        ActivateMapsProviderAction $activate,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $configuration = MapsProviderConfiguration::query()->firstOrFail();

        try {
            $configuration = $activate->handle($configuration, $context->actor($request));
        } catch (RuntimeException $exception) {
            return ApiResponse::error(
                $request,
                $exception->getMessage(),
                'The selected maps provider cannot be activated until its credentials are configured.',
                status: 422,
            );
        }

        return ApiResponse::success(
            $request,
            (new MapsProviderConfigurationResource($configuration))->resolve($request),
        );
    }

    public function deactivate(
        MapsProviderActionRequest $request,
        DeactivateMapsProviderAction $deactivate,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $deactivate->handle($context->actor($request));

        return ApiResponse::success($request, [
            'active' => false,
            'active_provider' => MapsProviderConfiguration::query()->value('active_provider') ?? 'leaflet',
        ]);
    }
}
