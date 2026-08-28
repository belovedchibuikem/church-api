<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CreateAlertRuleRequest;
use App\Http\Requests\Api\V1\Admin\EvaluateAlertRuleRequest;
use App\Http\Requests\Api\V1\Admin\ResolveAlertOccurrenceRequest;
use App\Http\Requests\Api\V1\Admin\SetAlertRuleEnabledRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Models\AlertOccurrence;
use App\Models\AlertRule;
use App\Reporting\Actions\AcknowledgeAlertOccurrenceAction;
use App\Reporting\Actions\CreateAlertRuleAction;
use App\Reporting\Actions\EvaluateAlertRuleAction;
use App\Reporting\Actions\ResolveAlertOccurrenceAction;
use App\Reporting\Actions\SetAlertRuleEnabledAction;
use App\Reporting\AlertEvaluationContext;
use App\Reporting\AlertSeverity;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\ScopeReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportingOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function storeRule(CreateAlertRuleRequest $request, CreateAlertRuleAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $scope = null;
        if ($request->validated('scope_type') !== null && $request->validated('scope_key') !== null) {
            $scope = new ScopeReference((string) $request->validated('scope_type'), (string) $request->validated('scope_key'));
        }
        $rule = $this->execute(fn (): AlertRule => $action->handle(
            (string) $request->validated('code'),
            (string) $request->validated('title'),
            (string) $request->validated('condition_type'),
            AlertSeverity::from((string) $request->validated('severity')),
            (array) $request->validated('configuration'),
            $context->actor($request),
            $scope,
        ));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($rule))->resolve($request), status: 201);
    }

    public function setEnabled(SetAlertRuleEnabledRequest $request, string $alertRule, SetAlertRuleEnabledAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = AlertRule::query()->where('public_id', $alertRule)->firstOrFail();
        $updated = $this->execute(fn (): AlertRule => $action->handle($target, (bool) $request->validated('enabled'), $context->actor($request)));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function evaluate(EvaluateAlertRuleRequest $request, string $alertRule, EvaluateAlertRuleAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = AlertRule::query()->where('public_id', $alertRule)->firstOrFail();
        $scope = null;
        if ($request->validated('scope_type') !== null && $request->validated('scope_key') !== null) {
            $scope = new ScopeReference((string) $request->validated('scope_type'), (string) $request->validated('scope_key'));
        }
        $occurrence = $this->execute(fn (): ?AlertOccurrence => $action->handle(
            $target,
            new AlertEvaluationContext(
                conditionReferenceType: (string) $request->validated('condition_reference_type'),
                conditionReferenceKey: (string) $request->validated('condition_reference_key'),
                scope: $scope,
                summary: $request->validated('summary'),
                facts: (array) ($request->validated('facts') ?? []),
            ),
            $context->actor($request),
        ));

        if ($occurrence === null) {
            return ApiResponse::success($request, null);
        }

        $occurrence->load(['rule:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($occurrence))->resolve($request), status: 201);
    }

    public function acknowledge(Request $request, string $occurrence, AcknowledgeAlertOccurrenceAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = AlertOccurrence::query()->where('public_id', $occurrence)->firstOrFail();
        $updated = $this->execute(fn (): AlertOccurrence => $action->handle($target, $context->actor($request)));
        $updated->load(['rule:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function resolve(ResolveAlertOccurrenceRequest $request, string $occurrence, ResolveAlertOccurrenceAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = AlertOccurrence::query()->where('public_id', $occurrence)->firstOrFail();
        $updated = $this->execute(fn (): AlertOccurrence => $action->handle(
            $target,
            (string) $request->validated('reason_code'),
            $context->actor($request),
        ));
        $updated->load(['rule:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }
}
