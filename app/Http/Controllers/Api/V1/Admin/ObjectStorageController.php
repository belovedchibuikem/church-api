<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ObjectStorageConnectionValidationException;
use App\Exceptions\ObjectStorageLocationInUseException;
use App\Exceptions\UnsafeObjectStorageEndpointException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ConfigureObjectStorageRequest;
use App\Http\Requests\Api\V1\Admin\ObjectStorageActionRequest;
use App\Http\Resources\Api\V1\Admin\ObjectStorageConfigurationResource;
use App\Models\ObjectStorageConfiguration;
use App\Services\Admin\ProtectedAdminContext;
use App\Storage\Actions\ActivateLocalStorageAction;
use App\Storage\Actions\ActivateObjectStorageAction;
use App\Storage\Actions\ConfigureS3ObjectStorageAction;
use App\Storage\Actions\ValidateObjectStorageConnectionAction;
use App\Storage\Data\S3ConnectionData;
use App\Storage\ObjectStorageDriver;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class ObjectStorageController extends Controller
{
    public function show(
        ObjectStorageActionRequest $request,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $configuration = $this->configuration();

        if ($configuration === null) {
            return ApiResponse::success($request, [
                'provider' => 's3',
                'configured' => false,
                'active' => false,
                'credentials_configured' => false,
                'active_provider' => 'local',
            ]);
        }

        $data = (new ObjectStorageConfigurationResource($configuration))->resolve($request);
        $data['active_provider'] = $configuration->is_active ? 's3' : 'local';

        return ApiResponse::success($request, $data);
    }

    public function configure(
        ConfigureObjectStorageRequest $request,
        ConfigureS3ObjectStorageAction $configure,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);

        try {
            $configuration = $configure->handle(new S3ConnectionData(
                accessKeyId: (string) $request->validated('access_key_id'),
                secretAccessKey: (string) $request->validated('secret_access_key'),
                region: (string) $request->validated('region'),
                bucket: (string) $request->validated('bucket'),
                endpoint: $request->validated('endpoint'),
                url: $request->validated('url'),
                rootPrefix: $request->validated('root_prefix'),
                usePathStyleEndpoint: (bool) $request->validated('use_path_style_endpoint', false),
            ), $context->actor($request));
        } catch (UnsafeObjectStorageEndpointException) {
            return ApiResponse::error(
                $request,
                'STORAGE_ENDPOINT_UNSAFE',
                'The object-storage endpoint is not allowed.',
                status: 422,
            );
        } catch (ObjectStorageLocationInUseException) {
            return ApiResponse::error(
                $request,
                'STORAGE_LOCATION_IN_USE',
                'The configured storage location is already referenced by file assets.',
                status: 409,
            );
        }

        return ApiResponse::success(
            $request,
            (new ObjectStorageConfigurationResource($configuration))->resolve($request),
        );
    }

    public function validateConnection(
        ObjectStorageActionRequest $request,
        ValidateObjectStorageConnectionAction $validate,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $configuration = ObjectStorageConfiguration::query()
            ->where('driver', ObjectStorageDriver::S3)
            ->firstOrFail();
        $result = $validate->handle($configuration, $context->actor($request));
        $configuration->refresh();

        return ApiResponse::success($request, [
            ...(new ObjectStorageConfigurationResource($configuration))->resolve($request),
            'validation_result' => [
                'status' => $result->status->value,
                'failure_code' => $result->failureCode,
            ],
        ]);
    }

    public function activate(
        ObjectStorageActionRequest $request,
        ActivateObjectStorageAction $activate,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $configuration = ObjectStorageConfiguration::query()
            ->where('driver', ObjectStorageDriver::S3)
            ->firstOrFail();

        try {
            $configuration = $activate->handle($configuration, $context->actor($request));
        } catch (ObjectStorageConnectionValidationException $exception) {
            return ApiResponse::error(
                $request,
                'STORAGE_VALIDATION_FAILED',
                'The object-storage connection could not be activated.',
                ['failure_code' => $exception->failureCode],
                503,
            );
        }

        return ApiResponse::success(
            $request,
            (new ObjectStorageConfigurationResource($configuration))->resolve($request),
        );
    }

    public function deactivate(
        ObjectStorageActionRequest $request,
        ActivateLocalStorageAction $activateLocal,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $activateLocal->handle($context->actor($request));

        return ApiResponse::success($request, [
            'active_provider' => 'local',
            'object_storage_active' => false,
        ]);
    }

    private function configuration(): ?ObjectStorageConfiguration
    {
        return ObjectStorageConfiguration::query()
            ->where('driver', ObjectStorageDriver::S3)
            ->first();
    }
}
