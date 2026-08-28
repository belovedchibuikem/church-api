<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\ListChurchesRequest;
use App\Http\Resources\Api\V1\Public\ChurchCollection;
use App\Http\Resources\Api\V1\Public\ChurchResource;
use App\Models\Church;
use App\Support\Api\ApiResponse;
use App\Support\Church\PublicChurchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChurchController extends Controller
{
    public function index(ListChurchesRequest $request, PublicChurchQuery $query): JsonResponse
    {
        $resource = new ChurchCollection(
            $query->paginate($request->filters(), $request->sort(), $request->perPage()),
        );

        return ApiResponse::success($request, $resource->resolve($request), $resource->paginationMeta());
    }

    public function show(Request $request, Church $church, PublicChurchQuery $query): JsonResponse
    {
        $resource = new ChurchResource($query->findPublicOrFail($church));

        return ApiResponse::success($request, $resource->resolve($request));
    }
}
