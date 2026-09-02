<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SyncKcaOrientationStepsRequest;
use App\Http\Resources\Api\V1\Admin\KcaOrientationStepResource;
use App\Models\KcaOrientationStep;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Kca\SyncKcaOrientationStepsAction;
use Illuminate\Http\JsonResponse;

class KcaOrientationStepController extends Controller
{
    public function index(ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal(request());

        $steps = KcaOrientationStep::query()->ordered()->get();

        return ApiResponse::success(request(), [
            'steps' => KcaOrientationStepResource::collection($steps)->resolve(),
        ]);
    }

    public function sync(
        SyncKcaOrientationStepsRequest $request,
        SyncKcaOrientationStepsAction $action,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);

        $steps = $action->handle($request->validated('steps'));

        return ApiResponse::success($request, [
            'steps' => KcaOrientationStepResource::collection($steps)->resolve(),
        ]);
    }
}
