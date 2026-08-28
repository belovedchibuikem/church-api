<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Support\Api\ApiResponse;
use App\Support\Identity\ResetPasswordAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    public function __invoke(
        ResetPasswordRequest $request,
        ResetPasswordAction $resetPassword,
    ): JsonResponse {
        $wasReset = $resetPassword->handle(
            (string) $request->validated('email'),
            (string) $request->validated('token'),
            (string) $request->validated('password'),
        );

        if (! $wasReset) {
            throw ValidationException::withMessages([
                'token' => ['The password reset request is invalid or has expired.'],
            ]);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success($request, ['password_reset' => true]);
    }
}
