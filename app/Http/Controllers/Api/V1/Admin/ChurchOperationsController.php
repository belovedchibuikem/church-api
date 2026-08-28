<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Church\HomeChurchApplicationStatus;
use App\Church\MeetingDay;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignPrayerRequestRequest;
use App\Http\Requests\Api\V1\Admin\CompleteFollowUpTaskRequest;
use App\Http\Requests\Api\V1\Admin\CreateAdminHomeChurchApplicationRequest;
use App\Http\Requests\Api\V1\Admin\CreateChurchRequest;
use App\Http\Requests\Api\V1\Admin\EndChurchMembershipRequest;
use App\Http\Requests\Api\V1\Admin\ListProtectedDomainRecordsRequest;
use App\Http\Requests\Api\V1\Admin\RegisterFirstTimerRequest;
use App\Http\Requests\Api\V1\Admin\StartChurchMembershipRequest;
use App\Http\Requests\Api\V1\Admin\TransitionHomeChurchApplicationRequest;
use App\Http\Requests\Api\V1\Admin\TransitionPastoralRecordRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedDomainRecordResource;
use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\FirstTimer;
use App\Models\FollowUpTask;
use App\Models\HomeChurch;
use App\Models\HomeChurchApplication;
use App\Models\Location;
use App\Models\PastoralNeed;
use App\Models\Person;
use App\Models\PrayerRequest;
use App\Models\PrayerRequestAssignment;
use App\Queries\Admin\ProtectedDomainCatalogQuery;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\ScopeReference;
use App\Support\Church\CompleteFollowUpTaskAction;
use App\Support\Church\CreateChurchAction;
use App\Support\Church\CreateHomeChurchApplicationAction;
use App\Support\Church\EndChurchMembershipAction;
use App\Support\Church\HomeChurchApplicationData;
use App\Support\Church\RegisterFirstTimerAction;
use App\Support\Church\StartChurchMembershipAction;
use App\Support\Church\TransitionHomeChurchApplicationAction;
use App\Support\Identity\PersonDisplayName;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ChurchOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function churches(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->churches($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function storeChurch(CreateChurchRequest $request, CreateChurchAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $location = Location::query()->where('public_id', $request->validated('location_id'))->firstOrFail();
        $unit = AdministrativeUnit::query()->where('public_id', $request->validated('administrative_unit_id'))->firstOrFail();
        $context->ensureContains($request, new ScopeReference('administrative_unit', $unit->public_id));
        $church = $this->execute(fn (): Church => $action->handle((string) $request->validated('name'), $location, $unit, $context->actor($request)));
        $church->load(['location:id,public_id,name', 'administrativeUnit:id,public_id,name']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($church))->resolve($request), status: 201);
    }

    public function homeChurches(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->homeChurches($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function applications(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->homeChurchApplications($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function transitionApplication(TransitionHomeChurchApplicationRequest $request, string $application, TransitionHomeChurchApplicationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $target = HomeChurchApplication::query()->where('public_id', $application)->firstOrFail();
        $church = Church::query()->findOrFail($target->church_id);
        $context->ensureContains($request, $church->scopeReference());
        $updated = $this->execute(fn (): HomeChurchApplication => $action->handle(
            $target,
            HomeChurchApplicationStatus::from((string) $request->validated('status')),
            (string) $request->validated('reason_code'),
            $context->actor($request),
        ));
        $updated->load(['church:id,public_id,name', 'homeChurch:id,public_id,name', ...PersonDisplayName::eager('applicant')]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function firstTimers(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->firstTimers($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function registerFirstTimer(RegisterFirstTimerRequest $request, RegisterFirstTimerAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $person = Person::query()->where('public_id', $request->validated('person_id'))->firstOrFail();
        $church = Church::query()->where('public_id', $request->validated('church_id'))->firstOrFail();
        $homeChurch = $request->validated('home_church_id') === null ? null : HomeChurch::query()->where('public_id', $request->validated('home_church_id'))->firstOrFail();
        $assignee = $request->validated('assigned_follow_up_person_id') === null ? null : Person::query()->where('public_id', $request->validated('assigned_follow_up_person_id'))->firstOrFail();
        $context->ensureContains($request, $church->scopeReference());
        $firstTimer = $this->execute(fn (): FirstTimer => $action->handle(
            $person, $church, $homeChurch, $assignee,
            $request->validated('registered_at') === null ? null : CarbonImmutable::parse((string) $request->validated('registered_at')),
            $context->actor($request),
        ));
        $firstTimer->load([...PersonDisplayName::eager(), 'church:id,public_id,name', 'homeChurch:id,public_id,name']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($firstTimer))->resolve($request), status: 201);
    }

    public function followUpTasks(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->followUpTasks($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function completeFollowUpTask(CompleteFollowUpTaskRequest $request, string $task, CompleteFollowUpTaskAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $target = FollowUpTask::query()->with('firstTimer.church')->where('public_id', $task)->firstOrFail();
        $context->ensureContains($request, $target->firstTimer->church->scopeReference());
        $updated = $this->execute(fn (): FollowUpTask => $action->handle($target, (string) $request->validated('reason_code'), $context->actor($request)));
        $updated->load([
            'firstTimer.church:id,public_id,name',
            'firstTimer.homeChurch:id,public_id,name',
            ...PersonDisplayName::eager('firstTimer.person'),
            ...PersonDisplayName::eager('assignedTo'),
        ]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function storeApplication(CreateAdminHomeChurchApplicationRequest $request, CreateHomeChurchApplicationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $church = Church::query()->where('public_id', $request->validated('church_id'))->firstOrFail();
        $context->ensureContains($request, $church->scopeReference());
        $application = $this->execute(fn (): HomeChurchApplication => $action->handle(new HomeChurchApplicationData(
            applicant: Person::query()->where('public_id', $request->validated('applicant_person_id'))->firstOrFail(),
            church: $church,
            location: Location::query()->where('public_id', $request->validated('location_id'))->firstOrFail(),
            administrativeUnit: AdministrativeUnit::query()->where('public_id', $request->validated('administrative_unit_id'))->firstOrFail(),
            proposedName: (string) $request->validated('proposed_name'),
            expectedParticipants: (int) $request->validated('expected_participants'),
            meetingDay: MeetingDay::from((string) $request->validated('meeting_day')),
            meetingTime: (string) $request->validated('meeting_time'),
            contactEmail: (string) $request->validated('contact_email'),
            contactPhone: (string) $request->validated('contact_phone'),
            guidelinesAgreedAt: CarbonImmutable::parse((string) $request->validated('guidelines_agreed_at')),
        ), $context->actor($request)));
        $application->load(['church:id,public_id,name', 'homeChurch:id,public_id,name', ...PersonDisplayName::eager('applicant')]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($application))->resolve($request), status: 201);
    }

    public function startMembership(StartChurchMembershipRequest $request, StartChurchMembershipAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $church = Church::query()->where('public_id', $request->validated('church_id'))->firstOrFail();
        $context->ensureContains($request, $church->scopeReference());
        $homeChurch = $request->validated('home_church_id') === null
            ? null
            : HomeChurch::query()->where('public_id', $request->validated('home_church_id'))->firstOrFail();
        $membership = $this->execute(fn (): ChurchMembership => $action->handle(
            Person::query()->where('public_id', $request->validated('person_id'))->firstOrFail(),
            $church,
            $homeChurch,
            $request->validated('joined_at') === null ? null : CarbonImmutable::parse((string) $request->validated('joined_at')),
            $context->actor($request),
        ));
        $membership->load([...PersonDisplayName::eager(), 'church:id,public_id,name', 'homeChurch:id,public_id,name']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($membership))->resolve($request), status: 201);
    }

    public function endMembership(EndChurchMembershipRequest $request, string $membership, EndChurchMembershipAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $target = ChurchMembership::query()->with('church')->where('public_id', $membership)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $updated = $this->execute(fn (): ChurchMembership => $action->handle(
            $target,
            (string) $request->validated('reason_code'),
            $context->actor($request),
        ));
        $updated->load([...PersonDisplayName::eager(), 'church:id,public_id,name', 'homeChurch:id,public_id,name']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function prayerRequests(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->prayerRequests($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function transitionPrayerRequest(TransitionPastoralRecordRequest $request, string $prayer, ProtectedAdminContext $context): JsonResponse
    {
        $status = (string) $request->validated('status');
        $this->assertPastoralStatus($status, ['open', 'assigned', 'rejected', 'answered']);
        $target = PrayerRequest::query()->with(['person.memberships.church', 'person.firstTimers.church'])->where('public_id', $prayer)->firstOrFail();
        $this->ensurePersonInChurchScope($request, $context, $target->person);
        $target->forceFill(['status' => $status])->save();
        $target->load(PersonDisplayName::eager());

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    public function assignPrayerRequest(AssignPrayerRequestRequest $request, string $prayer, ProtectedAdminContext $context): JsonResponse
    {
        $target = PrayerRequest::query()
            ->with(['person.memberships.church', 'person.firstTimers.church'])
            ->where('public_id', $prayer)
            ->firstOrFail();
        $assignee = Person::query()
            ->with(['memberships.church', 'firstTimers.church'])
            ->where('public_id', $request->validated('assigned_to_person_id'))
            ->firstOrFail();
        $this->ensurePersonInChurchScope($request, $context, $target->person);
        $this->ensurePersonInChurchScope($request, $context, $assignee);

        $assignment = DB::transaction(function () use ($target, $assignee, $request, $context): PrayerRequestAssignment {
            $now = now()->utc();
            $target->forceFill([
                'status' => 'assigned',
                'assigned_to_person_id' => $assignee->getKey(),
                'assigned_at' => $now,
            ])->save();

            return PrayerRequestAssignment::query()->create([
                'prayer_request_id' => $target->getKey(),
                'assigned_to_person_id' => $assignee->getKey(),
                'assigned_by_user_id' => $context->actor($request)->getKey(),
                'note' => $request->validated('note'),
                'assigned_at' => $now,
            ]);
        });

        $target->load([...PersonDisplayName::eager(), 'assignedTo.profile']);

        return ApiResponse::success($request, [
            'prayer_request' => (new ProtectedDomainRecordResource($target))->resolve($request),
            'assignment' => [
                'id' => $assignment->public_id,
                'assigned_at' => $assignment->assigned_at->toIso8601String(),
            ],
        ], status: 201);
    }

    public function pastoralNeeds(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->pastoralNeeds($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function transitionPastoralNeed(TransitionPastoralRecordRequest $request, string $need, ProtectedAdminContext $context): JsonResponse
    {
        $status = (string) $request->validated('status');
        $this->assertPastoralStatus($status, ['open', 'approved', 'rejected', 'closed']);
        $target = PastoralNeed::query()->with(['person.memberships.church', 'person.firstTimers.church'])->where('public_id', $need)->firstOrFail();
        $this->ensurePersonInChurchScope($request, $context, $target->person);
        $target->forceFill(['status' => $status])->save();
        $target->load(PersonDisplayName::eager());

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    /** @param array<int, string> $allowed */
    private function assertPastoralStatus(string $status, array $allowed): void
    {
        if (! in_array($status, $allowed, true)) {
            throw new UnprocessableEntityHttpException('Unsupported status for this record.');
        }
    }

    private function ensurePersonInChurchScope(Request $request, ProtectedAdminContext $context, ?Person $person): void
    {
        if ($person === null) {
            throw new NotFoundHttpException;
        }

        $church = $person->memberships->first()?->church ?? $person->firstTimers->first()?->church;
        if ($church !== null) {
            $context->ensureContains($request, $church->scopeReference());

            return;
        }

        if (! $context->isGlobal($context->scope($request))) {
            throw new NotFoundHttpException;
        }
    }

    private function page(ListProtectedDomainRecordsRequest $request, LengthAwarePaginator $paginator): JsonResponse
    {
        return ApiResponse::success($request, ProtectedDomainRecordResource::collection($paginator->getCollection())->resolve($request), [
            'pagination' => ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ]);
    }
}
