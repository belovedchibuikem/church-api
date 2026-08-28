<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\MobileRefreshRequest;
use App\Http\Resources\Api\V1\Auth\MobileCredentialResource;
use App\Support\Api\ApiResponse;
use App\Support\Security\RefreshMobileCredentialsAction;
use Illuminate\Http\JsonResponse;

class MobileRefreshController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        MobileRefreshRequest $request,
        RefreshMobileCredentialsAction $refresh,
    ): JsonResponse {
        $data = $request->validated();
        $credentials = $refresh->handle($data['refresh_token'], $data['device_identifier']);

        return ApiResponse::success(
            $request,
            (new MobileCredentialResource($credentials))->resolve($request),
        );
    }
}
