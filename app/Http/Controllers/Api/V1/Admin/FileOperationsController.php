<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Files\Actions\ApproveFileAssetAction;
use App\Files\Actions\StoreFileAssetAction;
use App\Files\Data\StoreFileAssetData;
use App\Files\FileAssetClassification;
use App\Files\FileAssetStreamResponse;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ApproveAdminFileAssetRequest;
use App\Http\Requests\Api\V1\Admin\StoreFileAssetRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Models\FileAsset;
use App\Models\Person;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function store(StoreFileAssetRequest $request, StoreFileAssetAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $owner = $request->validated('owner_person_id') === null
            ? null
            : Person::query()->where('public_id', $request->validated('owner_person_id'))->firstOrFail();
        $asset = $this->execute(fn (): FileAsset => $action->handle(new StoreFileAssetData(
            file: $file,
            purpose: (string) $request->validated('purpose'),
            classification: FileAssetClassification::from((string) $request->validated('classification')),
            idempotencyKey: (string) $request->validated('idempotency_key'),
            owner: $owner,
            actor: $context->actor($request),
        )));
        $asset->load(['owner:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($asset))->resolve($request), status: 201);
    }

    public function approve(ApproveAdminFileAssetRequest $request, string $file, ApproveFileAssetAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = FileAsset::query()->where('public_id', $file)->firstOrFail();
        $updated = $this->execute(fn (): FileAsset => $action->handle($target, $context->actor($request)));
        $updated->load(['owner:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function stream(
        Request $request,
        string $file,
        FileAssetStreamResponse $streams,
        ProtectedAdminContext $context,
    ): StreamedResponse {
        $context->ensureGlobal($request);
        $target = FileAsset::query()
            ->available()
            ->where('public_id', $file)
            ->firstOrFail();

        return $streams->handle($target, $request->boolean('download', true));
    }
}
