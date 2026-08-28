<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\MobileLoginRequest;
use App\Http\Resources\Api\V1\Auth\MobileCredentialResource;
use App\Support\Api\ApiResponse;
use App\Support\Security\AuthenticateMobileLoginAction;
use App\Support\Security\RegisterDeviceData;
use Illuminate\Http\JsonResponse;

class MobileLoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        MobileLoginRequest $request,
        AuthenticateMobileLoginAction $authenticate,
    ): JsonResponse {
        $data = $request->validated();
        $credentials = $authenticate->handle(
            email: $data['email'],
            password: $data['password'],
            deviceData: new RegisterDeviceData(
                identifier: $data['device_identifier'],
                label: $data['device_label'] ?? null,
                deviceType: $data['device_type'] ?? null,
                platform: $data['platform'] ?? null,
                appVersion: $data['app_version'] ?? null,
            ),
        );

        return ApiResponse::success(
            $request,
            (new MobileCredentialResource($credentials))->resolve($request),
        );
    }
}
