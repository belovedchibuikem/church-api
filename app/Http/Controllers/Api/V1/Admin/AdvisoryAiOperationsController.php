<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\AdvisoryAi\AdvisoryAiService;
use App\AdvisoryAi\AdvisoryRequest;
use App\AdvisoryAi\AdvisoryResponse;
use App\AdvisoryAi\Assistant;
use App\AdvisoryAi\UseCase;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\RequestAdminAdvisoryRequest;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdvisoryAiOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function advise(RequestAdminAdvisoryRequest $request, AdvisoryAiService $service, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $response = $this->execute(fn (): AdvisoryResponse => $service->advise(new AdvisoryRequest(
            assistant: Assistant::from((string) $request->validated('assistant')),
            useCase: UseCase::from((string) $request->validated('use_case')),
            instruction: (string) $request->validated('instruction'),
            context: (array) ($request->validated('context') ?? []),
        )));

        return ApiResponse::success($request, [
            'available' => $response->available,
            'recommendation' => $response->recommendation,
            'reason_code' => $response->reasonCode,
            'requires_human_decision' => $response->requiresHumanDecision,
            'metadata' => $response->metadata,
        ]);
    }
}
