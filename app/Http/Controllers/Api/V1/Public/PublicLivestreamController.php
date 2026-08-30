<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Public\PublicLivestreamResource;
use App\Livestream\LivestreamStatus;
use App\Models\Livestream;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicLivestreamController extends Controller
{
    public function current(Request $request): JsonResponse
    {
        $stream = Livestream::query()
            ->with(['church:id,public_id,name'])
            ->where('status', LivestreamStatus::Live->value)
            ->latest('starts_at')
            ->first();

        if ($stream === null) {
            $stream = Livestream::query()
                ->with(['church:id,public_id,name'])
                ->where('status', LivestreamStatus::Scheduled->value)
                ->where(function ($query): void {
                    $query->whereNull('starts_at')->orWhere('starts_at', '>=', now()->subDay()->utc());
                })
                ->orderBy('starts_at')
                ->first();
        }

        if ($stream === null) {
            $stream = Livestream::query()
                ->with(['church:id,public_id,name'])
                ->latest('starts_at')
                ->first();
        }

        return ApiResponse::success(
            $request,
            $stream === null ? null : (new PublicLivestreamResource($stream))->resolve($request),
        );
    }

    public function show(Request $request, string $livestream): JsonResponse
    {
        $stream = Livestream::query()
            ->with(['church:id,public_id,name'])
            ->where('public_id', $livestream)
            ->firstOrFail();

        return ApiResponse::success(
            $request,
            (new PublicLivestreamResource($stream))->resolve($request),
        );
    }
}
