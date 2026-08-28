<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Support\Api\ApiResponse;
use App\Support\Identity\SendPasswordResetLinkAction;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    public function __invoke(
        ForgotPasswordRequest $request,
        SendPasswordResetLinkAction $sendResetLink,
    ): JsonResponse {
        $sendResetLink->handle((string) $request->validated('email'));

        return ApiResponse::success(
            $request,
            ['password_reset_request_accepted' => true],
            status: 202,
        );
    }
}
