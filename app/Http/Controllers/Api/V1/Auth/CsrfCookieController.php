<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CsrfCookieController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->session()->has('_token')) {
            $request->session()->regenerateToken();
        }

        return ApiResponse::success($request, [
            'csrf_cookie' => true,
            'csrf_token' => $request->session()->token(),
        ]);
    }
}
