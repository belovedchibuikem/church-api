<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Identity\EndBrowserSessionAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __invoke(
        Request $request,
        EndBrowserSessionAction $endSession,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user('web');
        $endSession->handle($request, $user);

        return ApiResponse::success($request, ['authenticated' => false]);
    }
}
