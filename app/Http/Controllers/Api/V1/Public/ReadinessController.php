<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use App\Support\Health\ReadinessChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadinessController extends Controller
{
    public function __construct(
        private ReadinessChecker $readinessChecker,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->readinessChecker->check();

        if (! $result->ready) {
            return ApiResponse::error(
                $request,
                'SERVICE_NOT_READY',
                'The service is not ready to accept traffic.',
                ['checks' => $result->checks],
                503,
            );
        }

        return ApiResponse::success($request, $result->toArray());
    }
}
