<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\ListPublicCrusadesRequest;
use App\Http\Resources\Api\V1\Public\PublicCrusadeResource;
use App\Mission\Queries\FindPublicCrusadeQuery;
use App\Mission\Queries\ListPublicCrusadesQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCrusadeController extends Controller
{
    public function index(ListPublicCrusadesRequest $request, ListPublicCrusadesQuery $query): JsonResponse
    {
        $paginator = $query->execute($request->validated());
        $data = PublicCrusadeResource::collection($paginator->getCollection())->resolve($request);

        return ApiResponse::success($request, $data, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, string $crusade, FindPublicCrusadeQuery $query): JsonResponse
    {
        $resource = new PublicCrusadeResource($query->execute($crusade));

        return ApiResponse::success($request, $resource->resolve($request));
    }
}
