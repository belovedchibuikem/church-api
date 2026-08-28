<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterBrowserUserRequest;
use App\Http\Resources\Api\V1\Auth\CurrentBrowserUserResource;
use App\Support\Api\ApiResponse;
use App\Support\Identity\RegisterBrowserUserAction;
use App\Support\Identity\StartBrowserSessionAction;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function __invoke(
        RegisterBrowserUserRequest $request,
        RegisterBrowserUserAction $registerUser,
        StartBrowserSessionAction $startSession,
    ): JsonResponse {
        try {
            $user = $registerUser->handle($request->validated());
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'email' => ['An account cannot be created with the supplied details.'],
            ]);
        }

        $startSession->handle($request, $user, authenticate: true);

        return ApiResponse::success(
            $request,
            (new CurrentBrowserUserResource($user))->resolve($request),
            ['email_verification_required' => true],
            201,
        );
    }
}
