<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListPermissionsRequest;
use App\Http\Requests\Api\V1\Admin\ListRolesRequest;
use App\Http\Resources\Api\V1\Admin\PermissionResource;
use App\Http\Resources\Api\V1\Admin\RoleResource;
use App\Queries\Admin\ListPermissionsQuery;
use App\Queries\Admin\ListRolesQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class AccessCatalogController extends Controller
{
    public function roles(ListRolesRequest $request, ListRolesQuery $roles): JsonResponse
    {
        $validated = $request->validated();
        $paginator = $roles->paginate(
            $validated['filter']['search'] ?? null,
            $validated['sort'] ?? 'code',
            (int) ($validated['per_page'] ?? 25),
        );

        return ApiResponse::success(
            $request,
            RoleResource::collection($paginator->getCollection())->resolve($request),
            ['pagination' => $this->pagination($paginator)],
        );
    }

    public function permissions(ListPermissionsRequest $request, ListPermissionsQuery $permissions): JsonResponse
    {
        $validated = $request->validated();
        $paginator = $permissions->paginate(
            $validated['filter']['search'] ?? null,
            $validated['sort'] ?? 'code',
            (int) ($validated['per_page'] ?? 25),
        );

        return ApiResponse::success(
            $request,
            PermissionResource::collection($paginator->getCollection())->resolve($request),
            ['pagination' => $this->pagination($paginator)],
        );
    }

    /** @return array{current_page: int, per_page: int, last_page: int, total: int} */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }
}
