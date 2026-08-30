<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\MobileRegisterRequest;
use App\Http\Resources\Api\V1\Auth\MobileCredentialResource;
use App\Support\Api\ApiResponse;
use App\Support\Security\RegisterDeviceData;
use App\Support\Security\RegisterMobileUserAction;
use Illuminate\Http\JsonResponse;

class MobileRegisterController extends Controller
{
    public function __invoke(
        MobileRegisterRequest $request,
        RegisterMobileUserAction $register,
    ): JsonResponse {
        $data = $request->validated();

        $credentials = $register->handle(
            attributes: [
                'profile' => $data['profile'],
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
            ],
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
            status: 201,
        );
    }
}
