<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\BrowserLoginRequest;
use App\Http\Resources\Api\V1\Auth\CurrentBrowserUserResource;
use App\Support\Api\ApiResponse;
use App\Support\Identity\AuthenticateBrowserUserAction;
use App\Support\Identity\StartBrowserSessionAction;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __invoke(
        BrowserLoginRequest $request,
        AuthenticateBrowserUserAction $authenticateUser,
        StartBrowserSessionAction $startSession,
    ): JsonResponse {
        $user = $authenticateUser->handle(
            (string) $request->validated('email'),
            (string) $request->validated('password'),
            (bool) $request->validated('remember', false),
        );
        $startSession->handle($request, $user);

        return ApiResponse::success(
            $request,
            (new CurrentBrowserUserResource($user))->resolve($request),
            ['email_verification_required' => ! $user->hasVerifiedEmail()],
        );
    }
}
