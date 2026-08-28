<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\AdminProfileResource;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $user->loadMissing([
            'person.profile',
            'person.preference',
            'roleAssignments.role',
            'securitySessions',
        ]);

        return ApiResponse::success(
            $request,
            AdminProfileResource::make($user)->resolve($request),
        );
    }
}
