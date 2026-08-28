<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\RegisterGuardianRelationshipRequest;
use App\Http\Requests\Api\V1\Admin\ReportSafeguardingIncidentRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Models\GuardianRelationship;
use App\Models\Person;
use App\Models\SafeguardingIncident;
use App\Safeguarding\Actions\RegisterGuardianRelationshipAction;
use App\Safeguarding\Actions\ReportSafeguardingIncidentAction;
use App\Safeguarding\IncidentSeverity;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class SafeguardingOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function reportIncident(ReportSafeguardingIncidentRequest $request, ReportSafeguardingIncidentAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $subject = $request->validated('subject_person_id') === null
            ? null
            : Person::query()->where('public_id', $request->validated('subject_person_id'))->firstOrFail();
        $incident = $this->execute(fn (): SafeguardingIncident => $action->handle(
            (string) $request->validated('concern_type'),
            IncidentSeverity::from((string) $request->validated('severity')),
            (string) $request->validated('restricted_summary'),
            $subject,
            $context->actor($request),
            $request->validated('occurred_at') === null ? null : CarbonImmutable::parse((string) $request->validated('occurred_at')),
        ));
        $incident->load(['subject:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($incident))->resolve($request), status: 201);
    }

    public function registerGuardian(RegisterGuardianRelationshipRequest $request, RegisterGuardianRelationshipAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $guardian = Person::query()->where('public_id', $request->validated('guardian_person_id'))->firstOrFail();
        $child = Person::query()->where('public_id', $request->validated('child_person_id'))->firstOrFail();
        $relationship = $this->execute(fn (): GuardianRelationship => $action->handle(
            $guardian,
            $child,
            (string) $request->validated('relationship_type'),
            $context->actor($request),
        ));
        $relationship->load(['guardian:id,public_id', 'child:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($relationship))->resolve($request), status: 201);
    }
}
