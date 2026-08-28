<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Files\Actions\ApproveFileAssetAction;
use App\Files\Actions\StoreFileAssetAction;
use App\Files\Data\StoreFileAssetData;
use App\Files\FileAssetClassification;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreMediaAttachmentRequest;
use App\Http\Requests\Api\V1\Admin\UploadMediaAttachmentRequest;
use App\Http\Resources\Api\V1\Admin\MediaAttachmentResource;
use App\Media\Actions\AttachMediaAction;
use App\Media\Actions\DetachMediaAction;
use App\Media\MediaAttachableType;
use App\Media\MediaRole;
use App\Models\FileAsset;
use App\Models\MediaAttachment;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MediaOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function index(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $attachments = MediaAttachment::query()
            ->with(['fileAsset', 'attachable'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return ApiResponse::success(
            $request,
            MediaAttachmentResource::collection($attachments)->resolve($request),
        );
    }

    public function store(
        StoreMediaAttachmentRequest $request,
        AttachMediaAction $attach,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $attachable = $this->findAttachable(
            (string) $request->validated('attachable_type'),
            (string) $request->validated('attachable_id'),
        );
        $file = FileAsset::query()->where('public_id', $request->validated('file_asset_id'))->firstOrFail();
        $attachment = $this->execute(fn (): MediaAttachment => $attach->handle(
            $attachable,
            $file,
            MediaRole::from((string) $request->validated('role')),
            $context->actor($request),
        ));

        return ApiResponse::success(
            $request,
            (new MediaAttachmentResource($attachment->load(['fileAsset', 'attachable'])))->resolve($request),
            status: 201,
        );
    }

    public function upload(
        UploadMediaAttachmentRequest $request,
        StoreFileAssetAction $storeFile,
        ApproveFileAssetAction $approveFile,
        AttachMediaAction $attach,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $actor = $context->actor($request);
        $attachable = $this->findAttachable(
            (string) $request->validated('attachable_type'),
            (string) $request->validated('attachable_id'),
        );

        $attachment = $this->execute(function () use (
            $request,
            $file,
            $storeFile,
            $approveFile,
            $attach,
            $actor,
            $attachable,
        ): MediaAttachment {
            $asset = $storeFile->handle(new StoreFileAssetData(
                file: $file,
                purpose: (string) ($request->validated('purpose') ?? 'media.public'),
                classification: FileAssetClassification::from(
                    (string) ($request->validated('classification') ?? FileAssetClassification::Public->value),
                ),
                idempotencyKey: (string) $request->validated('idempotency_key'),
                owner: null,
                actor: $actor,
            ));
            $asset = $approveFile->handle($asset, $actor);

            return $attach->handle(
                $attachable,
                $asset,
                MediaRole::from((string) $request->validated('role')),
                $actor,
            );
        });

        return ApiResponse::success(
            $request,
            (new MediaAttachmentResource($attachment->load(['fileAsset', 'attachable'])))->resolve($request),
            status: 201,
        );
    }

    public function destroy(
        Request $request,
        string $media,
        DetachMediaAction $detach,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $attachment = MediaAttachment::query()
            ->with(['attachable', 'fileAsset'])
            ->where('public_id', $media)
            ->firstOrFail();
        $this->execute(function () use ($detach, $attachment, $context, $request): true {
            $detach->handle($attachment, $context->actor($request));

            return true;
        });

        return ApiResponse::success($request, ['removed' => true]);
    }

    private function findAttachable(string $type, string $publicId): Model
    {
        $class = MediaAttachableType::classFor($type);
        $record = $class::query()->where('public_id', $publicId)->first();
        if ($record === null) {
            throw new NotFoundHttpException;
        }

        return $record;
    }
}
