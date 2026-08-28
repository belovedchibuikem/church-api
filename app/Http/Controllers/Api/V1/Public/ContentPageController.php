<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Content\ContentPageResource;
use App\Http\Resources\Api\V1\Content\ContentPageSummaryResource;
use App\Models\ContentPage;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentPageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pages = ContentPage::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('slug')
            ->get(['id', 'public_id', 'slug', 'title', 'summary', 'locale', 'published_at']);

        return ApiResponse::success(
            $request,
            ContentPageSummaryResource::collection($pages)->resolve($request),
        );
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $page = ContentPage::query()
            ->where('slug', $slug)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['items' => function ($query): void {
                $query->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with('mediaAttachments.fileAsset');
            }, 'mediaAttachments.fileAsset'])
            ->firstOrFail();

        return ApiResponse::success(
            $request,
            ContentPageResource::make($page)->resolve($request),
        );
    }
}
