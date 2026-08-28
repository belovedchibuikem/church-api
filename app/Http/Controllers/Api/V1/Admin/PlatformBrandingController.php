<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Branding\Actions\RemovePlatformBrandAssetAction;
use App\Branding\Actions\UpdatePlatformBrandingAction;
use App\Branding\Actions\UploadPlatformBrandAssetAction;
use App\Branding\PlatformBrandAssetKind;
use App\Branding\PlatformBrandingPresenter;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdatePlatformBrandingRequest;
use App\Http\Requests\Api\V1\Admin\UploadPlatformBrandAssetRequest;
use App\Models\PlatformBrandingConfiguration;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class PlatformBrandingController extends Controller
{
    use ExecutesDomainMutations;

    public function show(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $configuration = PlatformBrandingConfiguration::query()->with(['logoFile', 'faviconFile'])->first();

        return ApiResponse::success($request, PlatformBrandingPresenter::toArray($configuration));
    }

    public function update(
        UpdatePlatformBrandingRequest $request,
        UpdatePlatformBrandingAction $update,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $configuration = $this->execute(fn (): PlatformBrandingConfiguration => $update->handle(
            (string) $request->validated('app_name'),
            $context->actor($request),
        ));

        return ApiResponse::success($request, PlatformBrandingPresenter::toArray($configuration));
    }

    public function uploadLogo(
        UploadPlatformBrandAssetRequest $request,
        UploadPlatformBrandAssetAction $upload,
        ProtectedAdminContext $context,
    ): JsonResponse {
        return $this->upload($request, $upload, $context, PlatformBrandAssetKind::Logo);
    }

    public function uploadFavicon(
        UploadPlatformBrandAssetRequest $request,
        UploadPlatformBrandAssetAction $upload,
        ProtectedAdminContext $context,
    ): JsonResponse {
        return $this->upload($request, $upload, $context, PlatformBrandAssetKind::Favicon);
    }

    public function destroyLogo(
        Request $request,
        RemovePlatformBrandAssetAction $remove,
        ProtectedAdminContext $context,
    ): JsonResponse {
        return $this->destroyAsset($request, $remove, $context, PlatformBrandAssetKind::Logo);
    }

    public function destroyFavicon(
        Request $request,
        RemovePlatformBrandAssetAction $remove,
        ProtectedAdminContext $context,
    ): JsonResponse {
        return $this->destroyAsset($request, $remove, $context, PlatformBrandAssetKind::Favicon);
    }

    private function upload(
        UploadPlatformBrandAssetRequest $request,
        UploadPlatformBrandAssetAction $upload,
        ProtectedAdminContext $context,
        PlatformBrandAssetKind $kind,
    ): JsonResponse {
        $context->ensureGlobal($request);
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $configuration = $this->execute(fn (): PlatformBrandingConfiguration => $upload->handle(
            $kind,
            $file,
            (string) $request->validated('idempotency_key'),
            $context->actor($request),
        ));

        return ApiResponse::success($request, PlatformBrandingPresenter::toArray($configuration));
    }

    private function destroyAsset(
        Request $request,
        RemovePlatformBrandAssetAction $remove,
        ProtectedAdminContext $context,
        PlatformBrandAssetKind $kind,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $configuration = $this->execute(fn (): PlatformBrandingConfiguration => $remove->handle(
            $kind,
            $context->actor($request),
        ));

        return ApiResponse::success($request, PlatformBrandingPresenter::toArray($configuration));
    }
}
