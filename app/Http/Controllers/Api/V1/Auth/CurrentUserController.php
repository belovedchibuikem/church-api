<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Auth\CurrentBrowserUserResource;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('web');
        $user->loadMissing('person.profile.avatarFileAsset');

        return ApiResponse::success(
            $request,
            (new CurrentBrowserUserResource($user))->resolve($request),
        );
    }
}
