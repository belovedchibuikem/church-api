<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CommunicationProviderActionRequest;
use App\Http\Requests\Api\V1\Admin\ConfigureCommunicationProviderRequest;
use App\Http\Resources\Api\V1\Admin\CommunicationProviderConfigurationResource;
use App\Models\CommunicationProviderConfiguration;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Communication\ActivateCommunicationProviderAction;
use App\Support\Communication\ConfigureCommunicationProviderAction;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class CommunicationProviderController extends Controller
{
    public function show(CommunicationProviderActionRequest $request, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $configuration = CommunicationProviderConfiguration::query()->first();

        if ($configuration === null) {
            return ApiResponse::success($request, [
                'configured' => false,
                'active' => false,
                'email' => ['provider' => 'none', 'credentials_configured' => false],
                'sms' => ['provider' => 'none', 'credentials_configured' => false],
                'whatsapp' => ['provider' => 'none', 'credentials_configured' => false],
                'push' => ['provider' => 'none', 'credentials_configured' => false],
                'consent_required_channels' => ['email', 'sms', 'whatsapp', 'push'],
                'retry' => ['max_attempts' => 3, 'backoff_seconds' => 60],
            ]);
        }

        return ApiResponse::success($request, (new CommunicationProviderConfigurationResource($configuration))->resolve($request));
    }

    public function configure(
        ConfigureCommunicationProviderRequest $request,
        ConfigureCommunicationProviderAction $configure,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $configuration = $configure->handle($request->validated(), $context->actor($request));

        return ApiResponse::success($request, (new CommunicationProviderConfigurationResource($configuration))->resolve($request));
    }

    public function activate(
        CommunicationProviderActionRequest $request,
        ActivateCommunicationProviderAction $activate,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);

        try {
            $configuration = $activate->handle(
                CommunicationProviderConfiguration::query()->firstOrFail(),
                $context->actor($request),
            );
        } catch (RuntimeException $exception) {
            return ApiResponse::error(
                $request,
                $exception->getMessage(),
                'Activate at least one email, SMS, WhatsApp, or push provider with credentials.',
                status: 422,
            );
        }

        return ApiResponse::success($request, (new CommunicationProviderConfigurationResource($configuration))->resolve($request));
    }

    public function deactivate(CommunicationProviderActionRequest $request, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $configuration = CommunicationProviderConfiguration::query()->first();
        if ($configuration !== null) {
            $configuration->forceFill(['is_active' => false, 'activated_at' => null])->save();
        }

        return ApiResponse::success($request, ['active' => false]);
    }
}
