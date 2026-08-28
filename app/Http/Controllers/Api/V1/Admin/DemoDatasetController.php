<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Demo\WipeDemoDatasetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\WipeDemoDatasetRequest;
use App\Models\DemoDataset;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoDatasetController extends Controller
{
    public function show(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        abort_if(app()->isProduction(), 404);
        $context->ensureGlobal($request);
        $dataset = DemoDataset::query()->where('dataset_key', DemoDataset::KEY)->first();

        return ApiResponse::success($request, [
            'seeded' => $dataset !== null,
            'dataset_key' => DemoDataset::KEY,
            'seeded_at' => $dataset?->seeded_at?->toIso8601String(),
            'summary' => $dataset?->summary ?? [],
            'confirmation_phrase' => 'ERASE DEMO',
            'accounts' => $dataset === null ? [] : [
                [
                    'email' => 'admin@familyhouse.demo',
                    'name' => 'Daniel David',
                    'role' => 'Platform administrator',
                ],
                [
                    'email' => 'pastor@familyhouse.demo',
                    'name' => 'Grace Ezekiel',
                    'role' => 'Church operations',
                ],
                [
                    'email' => 'member@familyhouse.demo',
                    'name' => 'Samuel David',
                    'role' => 'Member / KCA student',
                ],
            ],
        ]);
    }

    public function wipe(
        WipeDemoDatasetRequest $request,
        WipeDemoDatasetAction $wipe,
        ProtectedAdminContext $context,
    ): JsonResponse {
        abort_if(app()->isProduction(), 404);
        $context->ensureGlobal($request);
        $result = $wipe->handle($context->actor($request));

        return ApiResponse::success($request, $result);
    }
}
