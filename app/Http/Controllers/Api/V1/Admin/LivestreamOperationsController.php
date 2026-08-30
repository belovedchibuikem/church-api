<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Public\PublicLivestreamResource;
use App\Support\Api\ApiResponse;
use App\Support\Livestream\UpsertLivestreamAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class LivestreamOperationsController extends Controller
{
    public function upsert(Request $request, UpsertLivestreamAction $action): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'host_name' => ['nullable', 'string', 'max:120'],
            'youtube_url' => ['required', 'string', 'max:512'],
            'status' => ['nullable', 'string', 'in:scheduled,live,ended'],
            'starts_at' => ['nullable', 'date'],
            'church_id' => ['nullable', 'string', 'ulid'],
            'viewer_count' => ['nullable', 'integer', 'min:0'],
            'reaction_count' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $stream = $action->handle($validated);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return ApiResponse::success(
            $request,
            (new PublicLivestreamResource($stream))->resolve($request),
        );
    }
}
