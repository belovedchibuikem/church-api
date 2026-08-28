<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\MfaChallengeRequest;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Security\VerifyMfaChallengeAction;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

class MfaChallengeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        MfaChallengeRequest $request,
        VerifyMfaChallengeAction $verifyChallenge,
    ): JsonResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $securitySessionAttribute = $request->attributes->get('security_session');
        $securitySession = $securitySessionAttribute instanceof SecuritySession
            ? $securitySessionAttribute
            : null;
        $data = $request->validated();
        $verifiedSession = $verifyChallenge->handle(
            $user,
            $securitySession,
            $data['code'] ?? null,
            $data['recovery_code'] ?? null,
            $data['method_id'] ?? null,
        );
        $verifiedAt = $verifiedSession?->mfa_verified_at ?? now()->utc();

        if ($request->hasSession()) {
            $request->session()->put('auth.mfa_verified_at', $verifiedAt->toIso8601String());
        }

        return ApiResponse::success($request, [
            'mfa_verified_at' => $verifiedAt->toIso8601String(),
            'valid_until' => $verifiedAt->addSeconds(min((int) config('api.mfa.recent_seconds'), 43_200))->toIso8601String(),
        ]);
    }
}
