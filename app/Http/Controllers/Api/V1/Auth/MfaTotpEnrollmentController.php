<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\MfaTotpConfirmRequest;
use App\Http\Requests\Api\V1\Auth\MfaTotpSetupRequest;
use App\Http\Resources\Api\V1\Auth\MfaEnrollmentResource;
use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Security\ConfirmTotpEnrollmentAction;
use App\Support\Security\CreateTotpEnrollmentAction;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

class MfaTotpEnrollmentController extends Controller
{
    public function store(
        MfaTotpSetupRequest $request,
        CreateTotpEnrollmentAction $createEnrollment,
    ): JsonResponse {
        $user = $this->user($request);
        $securitySession = $this->securitySession($request);
        $enrollment = $createEnrollment->handle(
            $user,
            $securitySession,
            $request->validated()['label'] ?? null,
        );

        return ApiResponse::success(
            $request,
            (new MfaEnrollmentResource($enrollment))->resolve($request),
            status: 201,
        );
    }

    public function confirm(
        MfaTotpConfirmRequest $request,
        ConfirmTotpEnrollmentAction $confirmEnrollment,
    ): JsonResponse {
        $data = $request->validated();
        $method = $confirmEnrollment->handle(
            $this->user($request),
            $this->securitySession($request),
            $data['method_id'],
            $data['code'],
        );

        $this->recordBrowserMfa($request, $method->verified_at->toIso8601String());

        return ApiResponse::success($request, [
            'method_id' => $method->public_id,
            'method_type' => $method->method_type,
            'verified_at' => $method->verified_at->toIso8601String(),
            'recovery_codes_remaining' => $method->getAttribute('unused_recovery_codes_count'),
        ]);
    }

    private function user(MfaTotpSetupRequest|MfaTotpConfirmRequest $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    private function securitySession(MfaTotpSetupRequest|MfaTotpConfirmRequest $request): ?SecuritySession
    {
        $securitySession = $request->attributes->get('security_session');

        return $securitySession instanceof SecuritySession ? $securitySession : null;
    }

    private function recordBrowserMfa(MfaTotpConfirmRequest $request, string $verifiedAt): void
    {
        if ($request->hasSession()) {
            $request->session()->put('auth.mfa_verified_at', $verifiedAt);
        }
    }
}
