<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\VerifyEmailRequest;
use App\Support\Api\ApiResponse;
use App\Support\Identity\VerifyEmailAddressAction;
use Illuminate\Http\JsonResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(
        VerifyEmailRequest $request,
        VerifyEmailAddressAction $verifyEmail,
    ): JsonResponse {
        $verifyEmail->handle(
            (int) $request->validated('id'),
            (string) $request->validated('hash'),
        );

        return ApiResponse::success($request, ['email_verified' => true]);
    }
}
