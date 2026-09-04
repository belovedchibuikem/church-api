<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Exceptions\FileAssetUnavailableException;
use App\Files\Queries\OpenFileAssetStreamQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\ListPressPublicationsRequest;
use App\Http\Requests\Api\V1\Public\ShowPressPublicationRequest;
use App\Http\Resources\Api\V1\Public\PressPublicationResource;
use App\Models\FileAsset;
use App\Models\PressPublication;
use App\Press\Queries\PublicPressPublicationQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PressPublicationController extends Controller
{
    public function __construct(
        private readonly PublicPressPublicationQuery $publications,
        private readonly OpenFileAssetStreamQuery $fileAssetStreams,
    ) {}

    public function index(ListPressPublicationsRequest $request): JsonResponse
    {
        $publications = $this->publications->paginate(
            $request->filters(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::success(
            $request,
            PressPublicationResource::collection($publications->getCollection())->resolve($request),
            ['pagination' => [
                'current_page' => $publications->currentPage(),
                'per_page' => $publications->perPage(),
                'last_page' => $publications->lastPage(),
                'total' => $publications->total(),
                'from' => $publications->firstItem(),
                'to' => $publications->lastItem(),
            ]],
        );
    }

    public function show(
        ShowPressPublicationRequest $request,
        string $publicId,
    ): JsonResponse {
        $publication = $this->publications->findByPublicIdOrFail($publicId);

        return ApiResponse::success(
            $request,
            (new PressPublicationResource($publication))->resolve($request),
        );
    }

    public function download(ShowPressPublicationRequest $request, string $publicId): StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        $publication = $this->publications->findByPublicIdOrFail($publicId);
        $contentFileAssetId = PressPublication::query()
            ->whereKey($publication->getKey())
            ->value('content_file_asset_id');
        $contentSourceUrl = PressPublication::query()
            ->whereKey($publication->getKey())
            ->value('content_source_url');

        if ($contentFileAssetId === null) {
            if (is_string($contentSourceUrl) && $contentSourceUrl !== '') {
                return redirect()->away($contentSourceUrl);
            }
            abort(404);
        }

        $fileAsset = FileAsset::query()->available()->find($contentFileAssetId);

        if ($fileAsset === null) {
            abort(404);
        }

        try {
            $stream = $this->fileAssetStreams->handle($fileAsset);
        } catch (FileAssetUnavailableException) {
            abort(404);
        }

        return response()->streamDownload(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, $this->downloadFilename($publication, $fileAsset), [
            'Content-Type' => $fileAsset->detected_mime_type,
        ]);
    }

    private function downloadFilename(PressPublication $publication, FileAsset $fileAsset): string
    {
        $metadata = is_array($fileAsset->metadata) ? $fileAsset->metadata : [];
        $originalFilename = $metadata['original_filename'] ?? null;
        $candidate = is_string($originalFilename) && $originalFilename !== ''
            ? $originalFilename
            : $publication->title;

        $sanitized = Str::of($candidate)
            ->replace('\\', '/')
            ->afterLast('/')
            ->replaceMatches('/[\x00-\x1F\x7F]/u', '')
            ->replaceMatches('/[^\pL\pN._ -]+/u', '_')
            ->squish()
            ->trim('. ')
            ->limit(200, '')
            ->toString();

        return $sanitized === '' ? 'publication' : $sanitized;
    }
}
