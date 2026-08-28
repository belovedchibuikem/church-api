<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignMissionMentorRequest;
use App\Http\Requests\Api\V1\Admin\CaptureMissionSoulRequest;
use App\Http\Requests\Api\V1\Admin\CompleteMissionFollowUpRequest;
use App\Http\Requests\Api\V1\Admin\CreateMissionInvitationRequest;
use App\Http\Requests\Api\V1\Admin\ListProtectedDomainRecordsRequest;
use App\Http\Requests\Api\V1\Admin\RecordMissionFollowUpRequest;
use App\Http\Requests\Api\V1\Admin\TransitionMissionInvitationRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedDomainRecordResource;
use App\Mission\Actions\AssignSoulMentorAction;
use App\Mission\Actions\CaptureMissionSoulAction;
use App\Mission\Actions\CompleteSoulFollowUpAction;
use App\Mission\Actions\CreateMissionInvitationAction;
use App\Mission\Actions\RecordSoulFollowUpAction;
use App\Mission\Actions\TransitionMissionInvitationAction;
use App\Mission\Data\CaptureMissionSoulData;
use App\Mission\MissionInvitationStatus;
use App\Models\Crusade;
use App\Models\Location;
use App\Models\MentorAssignment;
use App\Models\MissionInvitation;
use App\Models\MissionSoulJourney;
use App\Models\MissionTeamAssignment;
use App\Models\Person;
use App\Queries\Admin\ProtectedDomainCatalogQuery;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\ScopeReference;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class MissionOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function crusades(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->crusades($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function souls(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->souls($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function captureSoul(CaptureMissionSoulRequest $request, string $crusade, CaptureMissionSoulAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $target = Crusade::query()->where('public_id', $crusade)->firstOrFail();
        $context->ensureContains($request, new ScopeReference('mission_crusade', $target->public_id));
        $person = $request->validated('person_id') === null ? null : Person::query()->where('public_id', $request->validated('person_id'))->firstOrFail();
        $journey = $action->handle($target, new CaptureMissionSoulData(
            idempotencyKey: (string) $request->validated('idempotency_key'),
            person: $person,
            givenName: $request->validated('given_name'),
            familyName: $request->validated('family_name'),
            middleName: $request->validated('middle_name'),
            preferredName: $request->validated('preferred_name'),
        ), $context->actor($request));
        $journey->load(['crusade:id,public_id,name', 'person:id,public_id']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($journey))->resolve($request), status: 201);
    }

    public function assignMentor(AssignMissionMentorRequest $request, string $soul, AssignSoulMentorAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $journey = $this->soul($request, $soul, $context);
        $teamAssignment = MissionTeamAssignment::query()->where('public_id', $request->validated('mission_team_assignment_id'))->firstOrFail();
        $assignment = $action->handle($journey, $teamAssignment, (string) $request->validated('idempotency_key'), $context->actor($request));
        $assignment->load(['soulJourney:id,public_id', 'teamAssignment:id,public_id']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($assignment))->resolve($request), status: 201);
    }

    public function recordFollowUp(RecordMissionFollowUpRequest $request, string $soul, RecordSoulFollowUpAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $journey = $this->soul($request, $soul, $context);
        $mentorAssignment = MentorAssignment::query()->where('public_id', $request->validated('mentor_assignment_id'))->firstOrFail();
        $interaction = $action->handle(
            $journey, $mentorAssignment,
            (string) $request->validated('channel_code'), (string) $request->validated('outcome_code'),
            CarbonImmutable::parse((string) $request->validated('occurred_at')),
            (string) $request->validated('idempotency_key'), $context->actor($request),
        );
        $interaction->load(['soulJourney:id,public_id', 'mentorAssignment:id,public_id']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($interaction))->resolve($request), status: 201);
    }

    public function completeFollowUp(CompleteMissionFollowUpRequest $request, string $soul, CompleteSoulFollowUpAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $journey = $this->soul($request, $soul, $context);
        $updated = $this->execute(fn (): MissionSoulJourney => $action->handle($journey, (string) $request->validated('reason_code'), $context->actor($request)));
        $updated->load(['crusade:id,public_id,name', 'person:id,public_id', 'mentorAssignment:id,public_id,mission_soul_journey_id']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function invitations(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->invitations($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function storeInvitation(CreateMissionInvitationRequest $request, CreateMissionInvitationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $crusade = Crusade::query()->where('public_id', $request->validated('crusade_id'))->firstOrFail();
        $context->ensureContains($request, new ScopeReference('mission_crusade', $crusade->public_id));
        $invitation = $this->execute(fn (): MissionInvitation => $action->handle(
            $crusade,
            Person::query()->where('public_id', $request->validated('requester_person_id'))->firstOrFail(),
            Location::query()->where('public_id', $request->validated('requested_location_id'))->firstOrFail(),
            $context->actor($request),
        ));
        $invitation->load(['crusade:id,public_id,name', 'requester:id,public_id', 'requestedLocation:id,public_id']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($invitation))->resolve($request), status: 201);
    }

    public function transitionInvitation(TransitionMissionInvitationRequest $request, string $invitation, TransitionMissionInvitationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $target = MissionInvitation::query()->with('crusade:id,public_id')->where('public_id', $invitation)->firstOrFail();
        if ($target->crusade === null) {
            $context->ensureGlobal($request);
        } else {
            $context->ensureContains($request, new ScopeReference('mission_crusade', $target->crusade->public_id));
        }
        $updated = $this->execute(fn (): MissionInvitation => $action->handle(
            $target,
            MissionInvitationStatus::from((string) $request->validated('status')),
            $request->validated('reason_code'),
            $context->actor($request),
        ));
        $updated->load(['crusade:id,public_id,name', 'requester:id,public_id', 'requestedLocation:id,public_id']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    private function soul(ListProtectedDomainRecordsRequest|AssignMissionMentorRequest|RecordMissionFollowUpRequest|CompleteMissionFollowUpRequest $request, string $publicId, ProtectedAdminContext $context): MissionSoulJourney
    {
        $journey = MissionSoulJourney::query()->with('crusade:id,public_id')->where('public_id', $publicId)->firstOrFail();
        $context->ensureContains($request, new ScopeReference('mission_crusade', $journey->crusade->public_id));

        return $journey;
    }

    private function page(ListProtectedDomainRecordsRequest $request, LengthAwarePaginator $paginator): JsonResponse
    {
        return ApiResponse::success($request, ProtectedDomainRecordResource::collection($paginator->getCollection())->resolve($request), [
            'pagination' => ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ]);
    }
}
