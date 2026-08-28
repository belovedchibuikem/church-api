<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\QueryAdminSearchRequest;
use App\Search\SearchQuery;
use App\Search\SearchResult;
use App\Search\SearchService;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class SearchOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function query(QueryAdminSearchRequest $request, SearchService $service, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $results = $this->execute(fn (): array => $service->search(
            new SearchQuery(
                term: (string) $request->validated('term'),
                resourceTypes: array_values((array) ($request->validated('resource_types') ?? [])),
                limit: (int) ($request->validated('limit') ?? 20),
            ),
            ['public', 'internal'],
        ));

        return ApiResponse::success($request, array_map(
            static fn (SearchResult $result): array => [
                'resource_type' => $result->resourceType,
                'resource_id' => $result->resourceId,
                'title' => $result->title,
                'summary' => $result->summary,
                'classification' => $result->classification,
                'metadata' => $result->metadata,
            ],
            $results,
        ));
    }
}
