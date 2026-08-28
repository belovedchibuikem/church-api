<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListProtectedDomainRecordsRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Services\Admin\ProtectedAdminContext;
use App\Services\Admin\ProtectedDomainRegistry;
use App\Support\Api\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class DomainCatalogController extends Controller
{
    public function index(
        ListProtectedDomainRecordsRequest $request,
        ProtectedDomainRegistry $registry,
        ProtectedAdminContext $context,
        string $catalog,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $registry->definition($catalog);

        return $this->page(
            $request,
            $registry->paginate(
                $catalog,
                $request->validated('filter', []),
                (int) $request->validated('per_page', 25),
            ),
        );
    }

    private function page(ListProtectedDomainRecordsRequest $request, LengthAwarePaginator $paginator): JsonResponse
    {
        return ApiResponse::success(
            $request,
            ProtectedCatalogRecordResource::collection($paginator->getCollection())->resolve($request),
            [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
        );
    }
}
