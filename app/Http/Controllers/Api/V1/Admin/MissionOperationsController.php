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
use App\Http\Requests\Api\V1\Admin\StoreCrusadeRequest;
use App\Http\Requests\Api\V1\Admin\TransitionCrusadeRequest;
use App\Http\Requests\Api\V1\Admin\TransitionMissionInvitationRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedDomainRecordResource;
use App\Mission\Actions\ArchiveCrusadeAction;
use App\Mission\Actions\AssignSoulMentorAction;
use App\Mission\Actions\CaptureMissionSoulAction;
use App\Mission\Actions\CompleteSoulFollowUpAction;
use App\Mission\Actions\ConnectSoulChurchAction;
use App\Mission\Actions\CreateCrusadeAction;
use App\Mission\Actions\CreateMissionInvitationAction;
use App\Mission\Actions\RecordSoulConversionAction;
use App\Mission\Actions\RecordSoulFollowUpAction;
use App\Mission\Actions\TransitionCrusadeAction;
use App\Mission\Actions\TransitionMissionInvitationAction;
use App\Mission\Actions\UpdateCrusadeAction;
use App\Mission\CrusadeStatus;
use App\Mission\Data\CaptureMissionSoulData;
use App\Mission\MissionInvitationStatus;
use App\Models\Church;
use App\Models\Crusade;
use App\Models\FollowUpInteraction;
use App\Models\Location;
use App\Models\MentorAssignment;
use App\Models\MissionInvitation;
use App\Models\MissionPartner;
use App\Models\MissionSoulJourney;
use App\Models\MissionSupportRequest;
use App\Models\MissionTeamAssignment;
use App\Models\Person;
use App\Queries\Admin\ProtectedDomainCatalogQuery;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\ScopeReference;
use App\Support\Identity\PersonDisplayName;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function crusades(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->crusades($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function showCrusade(Request $request, string $crusade, ProtectedAdminContext $context): JsonResponse
    {
        $target = Crusade::query()->with('location:id,public_id,name')->withCount('soulJourneys')->where('public_id', $crusade)->firstOrFail();
        $context->ensureContains($request, new ScopeReference('mission_crusade', $target->public_id));
        $payload = (new ProtectedDomainRecordResource($target))->resolve($request);
        $status = $target->status instanceof CrusadeStatus ? $target->status : CrusadeStatus::from((string) $target->status);
        $payload['allowed_transitions'] = array_map(static fn (CrusadeStatus $item): string => $item->value, $status->allowedTargets());

        return ApiResponse::success($request, $payload);
    }

    public function storeCrusade(StoreCrusadeRequest $request, CreateCrusadeAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureContains($request, $context->scope($request));
        $location = $request->validated('location_id') === null
            ? null
            : Location::query()->where('public_id', $request->validated('location_id'))->firstOrFail();
        $crusade = $this->execute(fn (): Crusade => $action->handle([
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'theme' => $request->validated('theme'),
            'purpose' => $request->validated('purpose'),
            'description' => $request->validated('description'),
            'timezone' => $request->validated('timezone'),
            'location_id' => $location?->getKey(),
            'starts_at' => $request->validated('starts_at') === null ? null : CarbonImmutable::parse((string) $request->validated('starts_at')),
            'ends_at' => $request->validated('ends_at') === null ? null : CarbonImmutable::parse((string) $request->validated('ends_at')),
        ], $context->actor($request)));
        $crusade->load('location:id,public_id,name');

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($crusade))->resolve($request), status: 201);
    }

    public function updateCrusade(StoreCrusadeRequest $request, string $crusade, UpdateCrusadeAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $target = Crusade::query()->where('public_id', $crusade)->firstOrFail();
        $context->ensureContains($request, new ScopeReference('mission_crusade', $target->public_id));
        $location = $request->validated('location_id') === null
            ? null
            : Location::query()->where('public_id', $request->validated('location_id'))->first();
        $updated = $this->execute(fn (): Crusade => $action->handle($target, [
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'theme' => $request->validated('theme'),
            'purpose' => $request->validated('purpose'),
            'description' => $request->validated('description'),
            'timezone' => $request->validated('timezone'),
            'location_id' => $location?->getKey(),
            'starts_at' => $request->validated('starts_at') === null ? null : CarbonImmutable::parse((string) $request->validated('starts_at')),
            'ends_at' => $request->validated('ends_at') === null ? null : CarbonImmutable::parse((string) $request->validated('ends_at')),
        ], $context->actor($request)));

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function transitionCrusade(TransitionCrusadeRequest $request, string $crusade, TransitionCrusadeAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $target = Crusade::query()->where('public_id', $crusade)->firstOrFail();
        $context->ensureContains($request, new ScopeReference('mission_crusade', $target->public_id));
        $updated = $this->execute(fn (): Crusade => $action->handle(
            $target,
            CrusadeStatus::from((string) $request->validated('status')),
            $request->validated('reason_code'),
            $context->actor($request),
        ));
        $updated->load('location:id,public_id,name');

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function archiveCrusade(Request $request, string $crusade, ArchiveCrusadeAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'reason_code' => ['required', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/'],
        ]);
        $target = Crusade::query()->where('public_id', $crusade)->firstOrFail();
        $context->ensureContains($request, new ScopeReference('mission_crusade', $target->public_id));
        $updated = $this->execute(fn (): Crusade => $action->handle($target, $data['reason_code'], $context->actor($request)));

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function souls(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->souls($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function showSoul(Request $request, string $soul, ProtectedAdminContext $context): JsonResponse
    {
        $journey = MissionSoulJourney::query()->with([
            'crusade:id,public_id,name',
            'connectedChurch:id,public_id,name',
            ...PersonDisplayName::eager(),
            'mentorAssignment:id,public_id,mission_soul_journey_id,mission_team_assignment_id,assigned_at,ended_at',
        ])->where('public_id', $soul)->firstOrFail();
        $context->ensureContains($request, new ScopeReference('mission_crusade', $journey->crusade->public_id));

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($journey))->resolve($request));
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

    public function convertSoul(Request $request, string $soul, RecordSoulConversionAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'reason_code' => ['required', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/'],
        ]);
        $journey = $this->soulById($request, $soul, $context);
        $updated = $this->execute(fn (): MissionSoulJourney => $action->handle($journey, $data['reason_code'], $context->actor($request)));
        $updated->load(['crusade:id,public_id,name', 'person:id,public_id']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function connectSoulChurch(Request $request, string $soul, ConnectSoulChurchAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
        ]);
        $journey = $this->soulById($request, $soul, $context);
        $church = Church::query()->where('public_id', $data['church_id'])->firstOrFail();
        $updated = $this->execute(fn (): MissionSoulJourney => $action->handle($journey, $church, $context->actor($request)));

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function soulFollowUps(Request $request, string $soul, ProtectedAdminContext $context): JsonResponse
    {
        $journey = $this->soulById($request, $soul, $context);
        $items = FollowUpInteraction::query()
            ->where('mission_soul_journey_id', $journey->getKey())
            ->latest('occurred_at')
            ->get();

        return ApiResponse::success($request, ProtectedDomainRecordResource::collection($items)->resolve($request));
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

    public function followUpGaps(Request $request, ProtectedAdminContext $context, ProtectedDomainCatalogQuery $catalog): JsonResponse
    {
        return ApiResponse::success($request, $catalog->followUpGaps($context->scope($request)));
    }

    public function invitations(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->invitations($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function storeInvitation(CreateMissionInvitationRequest $request, CreateMissionInvitationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $crusade = $request->validated('crusade_id') === null
            ? null
            : Crusade::query()->where('public_id', $request->validated('crusade_id'))->firstOrFail();
        if ($crusade !== null) {
            $context->ensureContains($request, new ScopeReference('mission_crusade', $crusade->public_id));
        } else {
            $context->ensureContains($request, $context->scope($request));
        }
        $location = $request->validated('requested_location_id') === null
            ? null
            : Location::query()->where('public_id', $request->validated('requested_location_id'))->firstOrFail();
        $invitation = $this->execute(fn (): MissionInvitation => $action->handle(
            $crusade,
            Person::query()->where('public_id', $request->validated('requester_person_id'))->firstOrFail(),
            $location,
            $context->actor($request),
            [
                'purpose' => $request->validated('purpose'),
                'expected_attendance' => $request->validated('expected_attendance'),
                'notes' => $request->validated('notes'),
                'application_data' => $request->validated('application_data'),
                'idempotency_key' => $request->validated('idempotency_key') ?? $request->header('Idempotency-Key'),
            ],
        ));
        $invitation->load(['crusade:id,public_id,name', 'requester:id,public_id', 'requestedLocation:id,public_id']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($invitation))->resolve($request), status: 201);
    }

    public function transitionInvitation(TransitionMissionInvitationRequest $request, string $invitation, TransitionMissionInvitationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $target = MissionInvitation::query()->with('crusade:id,public_id')->where('public_id', $invitation)->firstOrFail();
        if ($target->crusade === null) {
            $context->ensureContains($request, $context->scope($request));
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

    public function teamAssignments(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->teamAssignments($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function storeTeamAssignment(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'crusade_id' => ['required', 'ulid', 'exists:crusades,public_id'],
            'person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'role_code' => ['required', 'string', 'max:100'],
            'assigned_at' => ['nullable', 'date'],
        ]);
        $crusade = Crusade::query()->where('public_id', $data['crusade_id'])->firstOrFail();
        $context->ensureContains($request, new ScopeReference('mission_crusade', $crusade->public_id));
        $assignment = $this->execute(fn (): MissionTeamAssignment => MissionTeamAssignment::query()->create([
            'crusade_id' => $crusade->getKey(),
            'person_id' => Person::query()->where('public_id', $data['person_id'])->firstOrFail()->getKey(),
            'role_code' => $data['role_code'],
            'assigned_at' => isset($data['assigned_at']) ? CarbonImmutable::parse($data['assigned_at']) : now()->utc(),
        ]));
        $assignment->load(['crusade:id,public_id,name', ...PersonDisplayName::eager()]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($assignment))->resolve($request), status: 201);
    }

    public function endTeamAssignment(Request $request, string $assignment, ProtectedAdminContext $context): JsonResponse
    {
        $target = MissionTeamAssignment::query()->with('crusade:id,public_id')->where('public_id', $assignment)->firstOrFail();
        $context->ensureContains($request, new ScopeReference('mission_crusade', $target->crusade->public_id));
        $updated = $this->execute(function () use ($target): MissionTeamAssignment {
            $target->ended_at = now()->utc();
            $target->save();

            return $target;
        });
        $updated->load(['crusade:id,public_id,name', ...PersonDisplayName::eager()]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function partners(ListProtectedDomainRecordsRequest $request): JsonResponse
    {
        $paginator = MissionPartner::query()
            ->whereNull('archived_at')
            ->latest()
            ->paginate((int) $request->validated('per_page', 25));

        return ApiResponse::success($request, $paginator->getCollection()->map(fn (MissionPartner $partner): array => $this->partnerPayload($partner))->all(), [
            'pagination' => ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ]);
    }

    public function storePartner(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureContains($request, $context->scope($request));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'partner_type' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
            'geography' => ['nullable', 'string', 'max:191'],
            'contact_name' => ['nullable', 'string', 'max:191'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $partner = MissionPartner::query()->create($data);

        return ApiResponse::success($request, $this->partnerPayload($partner), status: 201);
    }

    public function supportRequests(ListProtectedDomainRecordsRequest $request): JsonResponse
    {
        $paginator = MissionSupportRequest::query()->with(['crusade:id,public_id,name'])->latest()->paginate((int) $request->validated('per_page', 25));

        return ApiResponse::success($request, $paginator->getCollection()->map(fn (MissionSupportRequest $item): array => $this->supportPayload($item))->all(), [
            'pagination' => ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ]);
    }

    public function storeSupportRequest(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureContains($request, $context->scope($request));
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'category' => ['nullable', 'string', 'max:80'],
            'priority' => ['nullable', 'string', 'max:40'],
            'amount_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'details' => ['nullable', 'string', 'max:10000'],
            'crusade_id' => ['nullable', 'ulid', 'exists:crusades,public_id'],
        ]);
        $crusade = isset($data['crusade_id']) ? Crusade::query()->where('public_id', $data['crusade_id'])->first() : null;
        $item = MissionSupportRequest::query()->create([
            'title' => $data['title'],
            'category' => $data['category'] ?? 'general',
            'priority' => $data['priority'] ?? 'normal',
            'amount_minor' => $data['amount_minor'] ?? null,
            'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null,
            'details' => $data['details'] ?? null,
            'crusade_id' => $crusade?->getKey(),
            'status' => 'submitted',
        ]);

        return ApiResponse::success($request, $this->supportPayload($item), status: 201);
    }

    public function transitionSupportRequest(Request $request, string $supportRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:submitted,reviewed,approved,assigned,fulfilled,closed,declined'],
            'reason_code' => ['nullable', 'string', 'max:100'],
        ]);
        $item = MissionSupportRequest::query()->where('public_id', $supportRequest)->firstOrFail();
        $item->status = $data['status'];
        $item->save();

        return ApiResponse::success($request, $this->supportPayload($item));
    }

    public function reportsSummary(Request $request, ProtectedAdminContext $context, ProtectedDomainCatalogQuery $catalog): JsonResponse
    {
        $scope = $context->scope($request);
        $crusades = $catalog->crusades($scope, [], 1);
        $souls = $catalog->souls($scope, [], 1);

        return ApiResponse::success($request, [
            'total_crusades' => $crusades->total(),
            'souls_captured' => $souls->total(),
            'metric_definitions' => [
                'souls_captured' => 'Distinct soul journeys with captured_at in scope. Capture is not conversion.',
                'souls_won' => 'Distinct journeys with converted_at set by an authorised conversion event.',
                'active_follow_ups' => 'Journeys in mentor_assigned or follow_up_active.',
                'total_crusades' => 'Distinct crusades in scope, excluding none by default except archived when filtered.',
            ],
        ]);
    }

    private function soul(ListProtectedDomainRecordsRequest|AssignMissionMentorRequest|RecordMissionFollowUpRequest|CompleteMissionFollowUpRequest $request, string $publicId, ProtectedAdminContext $context): MissionSoulJourney
    {
        return $this->soulById($request, $publicId, $context);
    }

    private function soulById(Request $request, string $publicId, ProtectedAdminContext $context): MissionSoulJourney
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

    /** @return array<string, mixed> */
    private function partnerPayload(MissionPartner $partner): array
    {
        return [
            'id' => $partner->public_id,
            'name' => $partner->name,
            'partner_type' => $partner->partner_type,
            'status' => $partner->status,
            'geography' => $partner->geography,
            'contact_name' => $partner->contact_name,
            'contact_email' => $partner->contact_email,
        ];
    }

    /** @return array<string, mixed> */
    private function supportPayload(MissionSupportRequest $item): array
    {
        return [
            'id' => $item->public_id,
            'title' => $item->title,
            'category' => $item->category,
            'priority' => $item->priority,
            'status' => $item->status,
            'amount_minor' => $item->amount_minor,
            'currency' => $item->currency,
            'crusade_id' => $item->crusade?->public_id,
            'details' => $item->details,
        ];
    }
}
