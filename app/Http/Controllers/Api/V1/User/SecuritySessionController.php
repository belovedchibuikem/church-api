<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\User\SecuritySessionResource;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Security\RevokeSecuritySessionAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecuritySessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $sessions = $user->securitySessions()
            ->with('device')
            ->latest('started_at')
            ->get();

        return ApiResponse::success(
            $request,
            SecuritySessionResource::collection($sessions)->resolve($request),
        );
    }

    public function destroy(
        Request $request,
        string $securitySession,
        RevokeSecuritySessionAction $revokeSession,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $ownedSession = $user->securitySessions()
            ->where('public_id', $securitySession)
            ->firstOrFail();
        $revokedSession = $revokeSession->handle($ownedSession, 'user_requested', $user);

        return ApiResponse::success(
            $request,
            SecuritySessionResource::make(
                $revokedSession->loadMissing('device'),
            )->resolve($request),
        );
    }
}
