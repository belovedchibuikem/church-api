<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListFeatureFlagsRequest;
use App\Http\Requests\Api\V1\Admin\ListPlatformConfigurationsRequest;
use App\Http\Requests\Api\V1\Admin\UpsertFeatureFlagRequest;
use App\Http\Requests\Api\V1\Admin\UpsertPlatformConfigurationRequest;
use App\Http\Resources\Api\V1\Admin\FeatureFlagResource;
use App\Http\Resources\Api\V1\Admin\PlatformConfigurationResource;
use App\Models\FeatureFlag;
use App\Platform\ConfigurationClassification;
use App\Platform\ConfigurationValueType;
use App\Queries\Admin\PlatformSettingsQuery;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\ScopeReference;
use App\Support\Platform\PlatformContext;
use App\Support\Platform\PlatformKey;
use App\Support\Platform\SetFeatureFlagStateAction;
use App\Support\Platform\UpsertFeatureFlagAction;
use App\Support\Platform\UpsertPlatformConfigurationAction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSettingsController extends Controller
{
    public function configurations(
        ListPlatformConfigurationsRequest $request,
        PlatformSettingsQuery $settings,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $validated = $request->validated();
        $paginator = $settings->paginateConfigurations(
            $context->scope($request),
            $validated['filter'] ?? [],
            $validated['sort'] ?? 'key',
            (int) ($validated['per_page'] ?? 25),
        );

        return ApiResponse::success(
            $request,
            PlatformConfigurationResource::collection($paginator->getCollection())->resolve($request),
            ['pagination' => $this->pagination($paginator)],
        );
    }

    public function upsertConfiguration(
        UpsertPlatformConfigurationRequest $request,
        UpsertPlatformConfigurationAction $upsert,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $configuration = $upsert->handle(
            new PlatformKey((string) $request->validated('key')),
            ConfigurationValueType::from((string) $request->validated('value_type')),
            ConfigurationClassification::from((string) $request->validated('classification')),
            $request->validated('value'),
            $this->platformContext($request, $context),
            $context->actor($request),
        );

        return ApiResponse::success(
            $request,
            (new PlatformConfigurationResource($configuration))->resolve($request),
        );
    }

    public function featureFlags(
        ListFeatureFlagsRequest $request,
        PlatformSettingsQuery $settings,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $validated = $request->validated();
        $paginator = $settings->paginateFeatureFlags(
            $context->scope($request),
            $validated['filter'] ?? [],
            $validated['sort'] ?? 'key',
            (int) ($validated['per_page'] ?? 25),
        );

        return ApiResponse::success(
            $request,
            FeatureFlagResource::collection($paginator->getCollection())->resolve($request),
            ['pagination' => $this->pagination($paginator)],
        );
    }

    public function upsertFeatureFlag(
        UpsertFeatureFlagRequest $request,
        UpsertFeatureFlagAction $upsert,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $flag = $upsert->handle(
            new PlatformKey((string) $request->validated('key')),
            $this->platformContext($request, $context),
            (int) $request->validated('rollout_percentage'),
            $context->actor($request),
            $request->validated('starts_at') === null
                ? null
                : CarbonImmutable::parse($request->validated('starts_at')),
            $request->validated('ends_at') === null
                ? null
                : CarbonImmutable::parse($request->validated('ends_at')),
        );

        return ApiResponse::success($request, (new FeatureFlagResource($flag))->resolve($request));
    }

    public function enableFeatureFlag(
        Request $request,
        FeatureFlag $featureFlag,
        SetFeatureFlagStateAction $setState,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $this->ensureRecordScope($request, $featureFlag->scope_type, $featureFlag->scope_key, $context);
        $flag = $setState->handle($featureFlag, true, $context->actor($request));

        return ApiResponse::success($request, (new FeatureFlagResource($flag))->resolve($request));
    }

    public function disableFeatureFlag(
        Request $request,
        FeatureFlag $featureFlag,
        SetFeatureFlagStateAction $setState,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $this->ensureRecordScope($request, $featureFlag->scope_type, $featureFlag->scope_key, $context);
        $flag = $setState->handle($featureFlag, false, $context->actor($request));

        return ApiResponse::success($request, (new FeatureFlagResource($flag))->resolve($request));
    }

    private function platformContext(Request $request, ProtectedAdminContext $context): PlatformContext
    {
        $scopeType = $request->input('scope_type');
        $scopeId = $request->input('scope_id');
        $scope = is_string($scopeType) && is_string($scopeId)
            ? new ScopeReference($scopeType, $scopeId)
            : null;

        if ($scope === null) {
            $context->ensureGlobal($request);
        } else {
            $context->ensureContains($request, $scope);
        }

        return new PlatformContext((string) $request->input('environment'), $scope);
    }

    private function ensureRecordScope(
        Request $request,
        ?string $scopeType,
        ?string $scopeId,
        ProtectedAdminContext $context,
    ): void {
        if ($scopeType === null || $scopeId === null) {
            $context->ensureGlobal($request);

            return;
        }

        $context->ensureContains($request, new ScopeReference($scopeType, $scopeId));
    }

    /** @return array{current_page: int, per_page: int, last_page: int, total: int} */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }
}
