<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\MobileAccessToken;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Security\RevokeMobileCredentialFamilyAction;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileLogoutController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        RevokeMobileCredentialFamilyAction $revokeFamily,
    ): JsonResponse {
        $accessToken = $request->attributes->get('mobile_access_credential');
        $user = $request->user();

        if (! $accessToken instanceof MobileAccessToken || ! $user instanceof User) {
            throw new AuthenticationException;
        }

        $revokeFamily->handle(
            $accessToken->securitySession,
            $accessToken->family_id,
            'user_logout',
            $user,
        );

        return ApiResponse::success($request, ['logged_out' => true]);
    }
}
