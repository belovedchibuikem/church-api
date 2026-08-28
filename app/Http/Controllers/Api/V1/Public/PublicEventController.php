<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\ListPublicEventsRequest;
use App\Http\Requests\Api\V1\Public\ShowPublicEventRequest;
use App\Http\Resources\Api\V1\Public\PublicEventResource;
use App\Queries\PublicEvents\FindPublicEventQuery;
use App\Queries\PublicEvents\ListPublicEventsQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class PublicEventController extends Controller
{
    public function index(ListPublicEventsRequest $request, ListPublicEventsQuery $query): JsonResponse
    {
        $events = $query->handle($request->validated());

        return ApiResponse::success(
            $request,
            PublicEventResource::collection($events->getCollection())->resolve($request),
            ['pagination' => [
                'current_page' => $events->currentPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'last_page' => $events->lastPage(),
                'next_page_url' => $events->nextPageUrl(),
                'previous_page_url' => $events->previousPageUrl(),
            ]],
        );
    }

    public function show(
        ShowPublicEventRequest $request,
        string $event,
        FindPublicEventQuery $query,
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (new PublicEventResource($query->handle($event)))->resolve($request),
        );
    }
}
