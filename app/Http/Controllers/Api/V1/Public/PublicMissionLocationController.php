<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\ListPublicMissionLocationsRequest;
use App\Http\Resources\Api\V1\Public\PublicMissionLocationResource;
use App\Mission\Queries\ListPublicMissionLocationsQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class PublicMissionLocationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ListPublicMissionLocationsRequest $request, ListPublicMissionLocationsQuery $query): JsonResponse
    {
        $paginator = $query->execute($request->validated());
        $data = PublicMissionLocationResource::collection($paginator->getCollection())->resolve($request);

        return ApiResponse::success($request, $data, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
