<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\CheckAuthorizationRequest;
use App\Models\User;
use App\Queries\User\ListUserCapabilitiesQuery;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\AuthorizationDecisionService;
use App\Support\Authorization\MobilePermissionAliasCatalog;
use App\Support\Authorization\ScopeReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class UserAuthorizationController extends Controller
{
    public function capabilities(Request $request, ListUserCapabilitiesQuery $query): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return ApiResponse::success($request, $query->handle($user));
    }

    public function check(
        CheckAuthorizationRequest $request,
        AuthorizationDecisionService $authorization,
        MobilePermissionAliasCatalog $aliases,
        ListUserCapabilitiesQuery $capabilities,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $clientPermission = (string) $request->validated('permission');
        $canonical = $aliases->canonicalize($clientPermission);
        $snapshot = $capabilities->handle($user);

        if (! in_array($canonical, $snapshot['permissions'], true)) {
            return ApiResponse::success($request, [
                'allowed' => false,
                'state' => 'forbidden',
                'permission' => $clientPermission,
                'canonical_permission' => $canonical,
                'reason' => 'permission_not_assigned',
            ]);
        }

        $scopeType = $request->validated('scope_type');
        $scopeKey = $request->validated('scope_id');

        // Capability snapshot is enough for unscoped mobile navigation checks.
        if (! is_string($scopeType) || ! is_string($scopeKey) || $scopeType === '' || $scopeKey === '') {
            return ApiResponse::success($request, [
                'allowed' => true,
                'state' => 'allowed',
                'permission' => $clientPermission,
                'canonical_permission' => $canonical,
                'reason' => 'capability_granted',
            ]);
        }

        try {
            $scope = new ScopeReference($scopeType, $scopeKey);
        } catch (InvalidArgumentException) {
            return ApiResponse::error(
                $request,
                'AUTH_SCOPE_INVALID',
                'The requested authorization scope is invalid.',
                status: 400,
            );
        }

        $decision = $authorization->decide($user, $canonical, $scope);

        return ApiResponse::success($request, [
            'allowed' => $decision->allowed,
            'state' => $decision->allowed ? 'allowed' : 'forbidden',
            'permission' => $clientPermission,
            'canonical_permission' => $canonical,
            'reason' => $decision->reason->value,
            'scope' => [
                'type' => $scope->type,
                'id' => $scope->key,
            ],
            'decision_id' => $decision->record->public_id,
        ]);
    }
}
