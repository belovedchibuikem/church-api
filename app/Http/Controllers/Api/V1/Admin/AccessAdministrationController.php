<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignRoleToUserRequest;
use App\Http\Requests\Api\V1\Admin\AssignScopeToRoleAssignmentRequest;
use App\Http\Requests\Api\V1\Admin\GrantPermissionToRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\RolePermission;
use App\Models\ScopeAssignment;
use App\Models\User;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\AssignRoleToUserAction;
use App\Support\Authorization\AssignScopeToRoleAssignmentAction;
use App\Support\Authorization\GrantPermissionToRoleAction;
use App\Support\Authorization\ScopeReference;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class AccessAdministrationController extends Controller
{
    use ExecutesDomainMutations;

    public function assignRole(AssignRoleToUserRequest $request, string $user, AssignRoleToUserAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = User::query()->where('public_id', $user)->firstOrFail();
        $role = Role::query()->where('public_id', $request->validated('role_id'))->firstOrFail();
        $assignment = $this->execute(fn (): RoleAssignment => $action->handle(
            $target,
            $role,
            $context->actor($request),
            $request->validated('expires_at') === null ? null : CarbonImmutable::parse((string) $request->validated('expires_at')),
        ));
        $assignment->load(['role:id,public_id,code', 'user:id,public_id']);

        return ApiResponse::success($request, [
            'id' => $assignment->public_id,
            'user_id' => $assignment->user?->public_id,
            'role_id' => $assignment->role?->public_id,
            'role_code' => $assignment->role?->code,
            'assigned_at' => $assignment->assigned_at?->utc()->toIso8601String(),
            'expires_at' => $assignment->expires_at?->utc()->toIso8601String(),
        ], status: 201);
    }

    public function assignScope(AssignScopeToRoleAssignmentRequest $request, string $roleAssignment, AssignScopeToRoleAssignmentAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = RoleAssignment::query()->where('public_id', $roleAssignment)->firstOrFail();
        $scopeAssignment = $this->execute(fn (): ScopeAssignment => $action->handle(
            $target,
            new ScopeReference((string) $request->validated('scope_type'), (string) $request->validated('scope_key')),
            $context->actor($request),
        ));

        return ApiResponse::success($request, [
            'id' => $scopeAssignment->public_id,
            'role_assignment_id' => $target->public_id,
            'scope_type' => $scopeAssignment->scope_type,
            'scope_key' => $scopeAssignment->scope_key,
        ], status: 201);
    }

    public function grantPermission(GrantPermissionToRoleRequest $request, string $role, GrantPermissionToRoleAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = Role::query()->where('public_id', $role)->firstOrFail();
        $permission = Permission::query()->where('public_id', $request->validated('permission_id'))->firstOrFail();
        $grant = $this->execute(fn (): RolePermission => $action->handle($target, $permission, $context->actor($request)));
        $grant->load(['role:id,public_id,code', 'permission:id,public_id,code']);

        return ApiResponse::success($request, [
            'id' => $grant->public_id,
            'role_id' => $grant->role?->public_id,
            'permission_id' => $grant->permission?->public_id,
            'permission_code' => $grant->permission?->code,
        ], status: 201);
    }
}
