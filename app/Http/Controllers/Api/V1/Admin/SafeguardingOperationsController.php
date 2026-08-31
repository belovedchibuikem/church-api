<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\RegisterChildProfileRequest;
use App\Http\Requests\Api\V1\Admin\RegisterGuardianRelationshipRequest;
use App\Http\Requests\Api\V1\Admin\ReportSafeguardingIncidentRequest;
use App\Http\Requests\Api\V1\Admin\UpdateChildProfileRestrictionsRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSafeguardingIncidentRequest;
use App\Http\Resources\Api\V1\Admin\MediaAttachmentResource;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Http\Resources\Api\V1\Admin\ProtectedDomainRecordResource;
use App\Models\ChildProfile;
use App\Models\GuardianRelationship;
use App\Models\Person;
use App\Models\SafeguardingIncident;
use App\Safeguarding\Actions\RegisterChildProfileAction;
use App\Safeguarding\Actions\RegisterGuardianRelationshipAction;
use App\Safeguarding\Actions\ReportSafeguardingIncidentAction;
use App\Safeguarding\Actions\UpdateSafeguardingIncidentAction;
use App\Safeguarding\IncidentSeverity;
use App\Safeguarding\MinorStatus;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Identity\PersonDisplayName;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SafeguardingOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function showIncident(
        Request $request,
        string $incident,
        ProtectedAdminContext $context,
        RecordAuditEventAction $recordAuditEvent,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $target = SafeguardingIncident::query()
            ->with([
                ...PersonDisplayName::eager('subject'),
                'reportedBy:id,public_id,name,email',
                'assignedTo:id,public_id,name,email',
                'mediaAttachments.fileAsset',
            ])
            ->where('public_id', $incident)
            ->firstOrFail();

        $payload = (new ProtectedDomainRecordResource($target))->resolve($request);
        $payload['restricted_summary'] = $target->restricted_summary;
        $payload['case_notes'] = is_array($target->case_notes) ? $target->case_notes : [];
        $payload['documents'] = MediaAttachmentResource::collection($target->mediaAttachments)->resolve($request);

        $this->execute(fn () => $recordAuditEvent->handle(new AuditEventData(
            action: 'safeguarding.incident.viewed',
            actor: $context->actor($request),
            targetType: 'safeguarding_incident',
            targetId: $target->public_id,
            metadata: [
                'severity' => $target->severity->value,
                'status' => $target->status->value,
            ],
        )));

        return ApiResponse::success($request, $payload);
    }

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

    public function updateIncident(
        UpdateSafeguardingIncidentRequest $request,
        string $incident,
        UpdateSafeguardingIncidentAction $action,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $target = SafeguardingIncident::query()->where('public_id', $incident)->firstOrFail();
        $changes = [];
        if ($request->exists('assigned_to_user_id')) {
            $changes['assigned_to_user_id'] = $request->validated('assigned_to_user_id');
        }
        if ($request->filled('severity')) {
            $changes['severity'] = IncidentSeverity::from((string) $request->validated('severity'));
        }
        if ($request->validated('status') === 'closed') {
            $changes['close'] = true;
        }
        if ($request->filled('note')) {
            $changes['note'] = (string) $request->validated('note');
        }

        $updated = $this->execute(fn (): SafeguardingIncident => $action->handle(
            $target,
            $changes,
            $context->actor($request),
        ));
        $updated->load([
            ...PersonDisplayName::eager('subject'),
            'reportedBy:id,public_id,name,email',
            'assignedTo:id,public_id,name,email',
        ]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function registerChildProfile(RegisterChildProfileRequest $request, RegisterChildProfileAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $person = Person::query()->where('public_id', $request->validated('person_id'))->firstOrFail();
        $profile = $this->execute(fn (): ChildProfile => $action->handle(
            $person,
            $request->validated('date_of_birth'),
            MinorStatus::from((string) ($request->validated('minor_status') ?? MinorStatus::ConfirmedMinor->value)),
            (bool) ($request->validated('direct_communication_restricted') ?? true),
            (bool) ($request->validated('media_use_restricted') ?? true),
            $context->actor($request),
        ));
        $profile->load(PersonDisplayName::eager('person'));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($profile))->resolve($request), status: 201);
    }

    public function updateChildProfile(
        UpdateChildProfileRestrictionsRequest $request,
        string $profile,
        RegisterChildProfileAction $action,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $target = ChildProfile::query()->with('person')->where('public_id', $profile)->firstOrFail();
        $updated = $this->execute(fn (): ChildProfile => $action->handle(
            $target->person,
            null,
            $target->minor_status,
            $request->exists('direct_communication_restricted')
                ? (bool) $request->validated('direct_communication_restricted')
                : (bool) $target->direct_communication_restricted,
            $request->exists('media_use_restricted')
                ? (bool) $request->validated('media_use_restricted')
                : (bool) $target->media_use_restricted,
            $context->actor($request),
        ));
        $updated->load(PersonDisplayName::eager('person'));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
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
