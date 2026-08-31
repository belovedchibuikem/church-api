<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListPermissionsRequest;
use App\Http\Requests\Api\V1\Admin\ListRolesRequest;
use App\Http\Requests\Api\V1\Admin\StoreRoleRequest;
use App\Http\Requests\Api\V1\Admin\UpdateRoleRequest;
use App\Http\Resources\Api\V1\Admin\PermissionResource;
use App\Http\Resources\Api\V1\Admin\RoleResource;
use App\Models\Role;
use App\Queries\Admin\ListPermissionsQuery;
use App\Queries\Admin\ListRolesQuery;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\ManageRoleAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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

    public function showRole(string $role): JsonResponse
    {
        $record = Role::query()
            ->withCount('assignments')
            ->with(['rolePermissions.permission:id,public_id,code'])
            ->where('public_id', $role)
            ->firstOrFail();

        return ApiResponse::success(request(), (new RoleResource($record))->resolve(request()));
    }

    public function storeRole(StoreRoleRequest $request, ManageRoleAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        try {
            $role = $action->create((string) $request->validated('code'), (string) $request->validated('name'), $context->actor($request));
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException(previous: $exception);
        }
        $role->loadCount('assignments')->load(['rolePermissions.permission:id,public_id,code']);

        return ApiResponse::success($request, (new RoleResource($role))->resolve($request), status: 201);
    }

    public function updateRole(UpdateRoleRequest $request, string $role, ManageRoleAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $record = Role::query()->where('public_id', $role)->firstOrFail();
        try {
            $updated = $action->update($record, (string) $request->validated('name'), $context->actor($request));
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException(previous: $exception);
        }
        $updated->loadCount('assignments')->load(['rolePermissions.permission:id,public_id,code']);

        return ApiResponse::success($request, (new RoleResource($updated))->resolve($request));
    }

    public function destroyRole(string $role, ManageRoleAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $request = request();
        $context->ensureGlobal($request);
        $record = Role::query()->where('public_id', $role)->firstOrFail();
        $action->archive($record, $context->actor($request));

        return ApiResponse::success($request, null);
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
