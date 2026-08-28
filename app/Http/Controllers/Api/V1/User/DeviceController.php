<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\User\DeviceResource;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Security\RevokeDeviceAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return ApiResponse::success(
            $request,
            DeviceResource::collection(
                $user->devices()->latest('last_seen_at')->get(),
            )->resolve($request),
        );
    }

    public function destroy(
        Request $request,
        string $device,
        RevokeDeviceAction $revokeDevice,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $ownedDevice = $user->devices()
            ->where('public_id', $device)
            ->firstOrFail();
        $revokedDevice = $revokeDevice->handle($ownedDevice, 'user_requested', $user);

        return ApiResponse::success(
            $request,
            DeviceResource::make($revokedDevice)->resolve($request),
        );
    }
}
