<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success($request, [
            'name' => 'Family House Connect API',
            'status' => 'available',
            'surfaces' => ['public', 'user', 'admin'],
        ]);
    }
}
