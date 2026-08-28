<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequirePermissionAndScope;
use App\Http\Requests\Api\V1\Admin\ListScopeAssignmentsRequest;
use App\Http\Resources\Api\V1\Admin\ScopeAssignmentResource;
use App\Queries\Admin\ListScopeAssignmentsQuery;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\ScopeReference;
use Illuminate\Http\JsonResponse;
use LogicException;

class ScopeAssignmentController extends Controller
{
    public function __invoke(
        ListScopeAssignmentsRequest $request,
        ListScopeAssignmentsQuery $scopeAssignments,
    ): JsonResponse {
        $validated = $request->validated();
        $scope = $request->attributes->get(RequirePermissionAndScope::SCOPE_ATTRIBUTE);

        if (! $scope instanceof ScopeReference) {
            throw new LogicException('The protected route did not supply an authorization scope.');
        }

        $paginator = $scopeAssignments->paginate(
            $scope,
            $validated['filter'] ?? [],
            $validated['sort'] ?? '-created_at',
            (int) ($validated['per_page'] ?? 25),
        );

        return ApiResponse::success(
            $request,
            ScopeAssignmentResource::collection($paginator->getCollection())->resolve($request),
            ['pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ]],
        );
    }
}
