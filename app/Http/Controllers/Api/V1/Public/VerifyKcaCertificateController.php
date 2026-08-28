<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\VerifyKcaCertificateRequest;
use App\Http\Resources\Api\V1\Public\KcaCertificateVerificationResource;
use App\Queries\Kca\VerifyKcaCertificateQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class VerifyKcaCertificateController extends Controller
{
    public function __invoke(
        VerifyKcaCertificateRequest $request,
        VerifyKcaCertificateQuery $query,
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            (new KcaCertificateVerificationResource(
                $query->handle((string) $request->validated('code')),
            ))->resolve($request),
        );
    }
}
