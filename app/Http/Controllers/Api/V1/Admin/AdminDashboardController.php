<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Admin\AdminDashboardModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ShowAdminDashboardRequest;
use App\Queries\Admin\AdminDashboardQuery;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminDashboardController extends Controller
{
    public function show(
        ShowAdminDashboardRequest $request,
        string $module,
        AdminDashboardQuery $query,
        ProtectedAdminContext $context,
    ): JsonResponse {
        try {
            $dashboard = AdminDashboardModule::fromRoute($module);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException;
        }

        return ApiResponse::success(
            $request,
            $query->summarize(
                $dashboard,
                $context->scope($request),
                $request->period(),
                $request->currency(),
            ),
        );
    }
}
