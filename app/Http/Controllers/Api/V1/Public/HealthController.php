<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success($request, [
            'status' => 'ok',
            'service' => 'family-house-connect-api',
        ]);
    }
}
