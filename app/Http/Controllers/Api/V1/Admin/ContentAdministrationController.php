<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreContentItemRequest;
use App\Http\Requests\Api\V1\Admin\StoreContentPageRequest;
use App\Http\Requests\Api\V1\Admin\UpdateContentPageRequest;
use App\Http\Resources\Api\V1\Content\ContentItemResource;
use App\Http\Resources\Api\V1\Content\ContentPageResource;
use App\Models\ContentItem;
use App\Models\ContentPage;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentAdministrationController extends Controller
{
    public function index(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $pages = ContentPage::query()
            ->with(['items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')->with('mediaAttachments.fileAsset'), 'mediaAttachments.fileAsset'])
            ->orderBy('slug')
            ->limit(200)
            ->get();

        return ApiResponse::success(
            $request,
            ContentPageResource::collection($pages)->resolve($request),
        );
    }

    public function store(
        StoreContentPageRequest $request,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $validated = $request->validated();

        $page = new ContentPage;
        $page->forceFill([
            'slug' => $validated['slug'],
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'body' => $validated['body'],
            'locale' => $validated['locale'] ?? 'en',
            'published_at' => $validated['published_at'] ?? null,
        ])->save();

        return ApiResponse::success(
            $request,
            ContentPageResource::make($page->load('items'))->resolve($request),
            status: 201,
        );
    }

    public function update(
        UpdateContentPageRequest $request,
        string $page,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $target = ContentPage::query()->where('public_id', $page)->firstOrFail();
        $validated = $request->validated();
        $payload = [];

        foreach (['slug', 'title', 'body', 'locale'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }
        if (array_key_exists('summary', $validated)) {
            $payload['summary'] = $validated['summary'];
        }
        if (array_key_exists('published_at', $validated)) {
            $payload['published_at'] = $validated['published_at'];
        }

        if ($payload !== []) {
            $target->forceFill($payload)->save();
        }

        return ApiResponse::success(
            $request,
            ContentPageResource::make($target->fresh()->load('items'))->resolve($request),
        );
    }

    public function storeItem(
        StoreContentItemRequest $request,
        string $page,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $target = ContentPage::query()->where('public_id', $page)->firstOrFail();
        $validated = $request->validated();

        $item = new ContentItem;
        $item->forceFill([
            'page_id' => $target->getKey(),
            'kind' => $validated['kind'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'meta' => $validated['meta'] ?? null,
            'href' => $validated['href'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'published_at' => $validated['published_at'] ?? null,
        ])->save();

        return ApiResponse::success(
            $request,
            ContentItemResource::make($item)->resolve($request),
            status: 201,
        );
    }
}
