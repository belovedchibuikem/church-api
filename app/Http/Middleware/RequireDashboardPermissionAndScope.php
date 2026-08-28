<?php

namespace App\Http\Middleware;

use App\Admin\AdminDashboardModule;
use App\Admin\AdminDashboardPermissionCatalog;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\AuthorizationDecisionService;
use App\Support\Authorization\ScopeReference;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class RequireDashboardPermissionAndScope
{
    public function __construct(
        private readonly AuthorizationDecisionService $authorization,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }

        $module = (string) $request->route('module');

        try {
            $dashboard = AdminDashboardModule::fromRoute($module);
        } catch (InvalidArgumentException) {
            return ApiResponse::error(
                $request,
                'DASHBOARD_MODULE_UNKNOWN',
                'The requested dashboard module is invalid.',
                status: 404,
            );
        }

        $scopeType = $request->header('X-Scope-Type');
        $scopeId = $request->header('X-Scope-ID');

        if (! is_string($scopeType) || ! is_string($scopeId) || $scopeType === '' || $scopeId === '') {
            return ApiResponse::error(
                $request,
                'AUTH_SCOPE_REQUIRED',
                'X-Scope-Type and X-Scope-ID headers are required.',
                status: 400,
            );
        }

        try {
            $scope = new ScopeReference($scopeType, $scopeId);
        } catch (InvalidArgumentException) {
            return ApiResponse::error(
                $request,
                'AUTH_SCOPE_INVALID',
                'The requested authorization scope is invalid.',
                status: 400,
            );
        }

        $decision = null;
        foreach (AdminDashboardPermissionCatalog::acceptablePermissions($dashboard) as $permissionCode) {
            $candidate = $this->authorization->decide($actor, $permissionCode, $scope);
            if ($candidate->allowed) {
                $decision = $candidate;
                break;
            }
        }

        if ($decision === null) {
            return ApiResponse::error(
                $request,
                'AUTH_PERMISSION_DENIED',
                'You are not authorized to perform this action.',
                status: 403,
            );
        }

        $request->attributes->set(RequirePermissionAndScope::SCOPE_ATTRIBUTE, $scope);
        $request->attributes->set(RequirePermissionAndScope::DECISION_ATTRIBUTE, $decision->record->public_id);

        return $next($request);
    }
}
