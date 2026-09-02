<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Church\FollowUpTaskStatus;
use App\Church\FollowUpTaskType;
use App\Church\HomeChurchApplicationStatus;
use App\Church\HomeChurchStatus;
use App\Church\MeetingDay;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignPrayerRequestRequest;
use App\Http\Requests\Api\V1\Admin\CompleteFollowUpTaskRequest;
use App\Http\Requests\Api\V1\Admin\CreateAdminHomeChurchApplicationRequest;
use App\Http\Requests\Api\V1\Admin\CreateChurchRequest;
use App\Http\Requests\Api\V1\Admin\CreateHomeChurchRequest;
use App\Http\Requests\Api\V1\Admin\EndChurchMembershipRequest;
use App\Http\Requests\Api\V1\Admin\ListProtectedDomainRecordsRequest;
use App\Http\Requests\Api\V1\Admin\RegisterFirstTimerRequest;
use App\Http\Requests\Api\V1\Admin\StartChurchMembershipRequest;
use App\Http\Requests\Api\V1\Admin\TransitionHomeChurchApplicationRequest;
use App\Http\Requests\Api\V1\Admin\TransitionPastoralRecordRequest;
use App\Http\Requests\Api\V1\Admin\UpdateChurchRequest;
use App\Http\Requests\Api\V1\Admin\UpdateFirstTimerRequest;
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
use App\Support\Church\CreateHomeChurchAction;
use App\Support\Church\CreateHomeChurchApplicationAction;
use App\Support\Church\DeleteChurchAction;
use App\Support\Church\DeleteFirstTimerAction;
use App\Support\Church\EndChurchMembershipAction;
use App\Support\Church\HomeChurchApplicationData;
use App\Support\Church\RegisterFirstTimerAction;
use App\Support\Church\StartChurchMembershipAction;
use App\Support\Church\TransitionHomeChurchApplicationAction;
use App\Support\Church\UpdateChurchAction;
use App\Support\Church\UpdateChurchStatusAction;
use App\Support\Church\UpdateFirstTimerAction;
use App\Support\Church\UpdateHomeChurchAction;
use App\Support\Church\UpdateHomeChurchStatusAction;
use App\Support\Identity\PersonDisplayName;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ChurchOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function churches(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->churches($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function showChurch(Request $request, string $church, ProtectedAdminContext $context): JsonResponse
    {
        $target = Church::query()
            ->with([
                'location:id,public_id,country_id,administrative_unit_id,name,address_line_one,address_line_two,locality,postal_code,timezone',
                'location.country:id,public_id,iso_code,name',
                'administrativeUnit:id,public_id,name',
            ])
            ->withCount(['homeChurches', 'memberships', 'firstTimers', 'homeChurchApplications'])
            ->where('public_id', $church)
            ->firstOrFail();
        $context->ensureContains($request, $target->scopeReference());

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    public function storeChurch(CreateChurchRequest $request, CreateChurchAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $location = Location::query()->where('public_id', $request->validated('location_id'))->firstOrFail();
        $unit = AdministrativeUnit::query()->where('public_id', $request->validated('administrative_unit_id'))->firstOrFail();
        $context->ensureContains($request, new ScopeReference('administrative_unit', $unit->public_id));
        $church = $this->execute(fn (): Church => $action->handle((string) $request->validated('name'), $location, $unit, $context->actor($request)));
        $church->load([
            'location:id,public_id,country_id,administrative_unit_id,name,address_line_one,address_line_two,locality,postal_code,timezone',
            'location.country:id,public_id,iso_code,name',
            'administrativeUnit:id,public_id,name',
        ]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($church))->resolve($request), status: 201);
    }

    public function updateChurch(UpdateChurchRequest $request, string $church, UpdateChurchAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $target = Church::query()->where('public_id', $church)->firstOrFail();
        $context->ensureContains($request, $target->scopeReference());
        $location = Location::query()->where('public_id', $request->validated('location_id'))->firstOrFail();
        $unit = AdministrativeUnit::query()->where('public_id', $request->validated('administrative_unit_id'))->firstOrFail();
        $context->ensureContains($request, new ScopeReference('administrative_unit', $unit->public_id));
        $updated = $this->execute(fn (): Church => $action->handle(
            $target,
            (string) $request->validated('name'),
            $location,
            $unit,
            $context->actor($request),
        ));
        $updated->load([
            'location:id,public_id,country_id,administrative_unit_id,name,address_line_one,address_line_two,locality,postal_code,timezone',
            'location.country:id,public_id,iso_code,name',
            'administrativeUnit:id,public_id,name',
        ]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function updateChurchStatus(Request $request, string $church, UpdateChurchStatusAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:active,published,unpublished,suspended,closed'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $target = Church::query()->where('public_id', $church)->firstOrFail();
        $context->ensureContains($request, $target->scopeReference());
        $updated = $this->execute(fn (): Church => $action->handle(
            $target,
            $data['status'],
            $data['reason'],
            $context->actor($request),
        ));
        $updated->load([
            'location:id,public_id,country_id,administrative_unit_id,name,address_line_one,address_line_two,locality,postal_code,timezone',
            'location.country:id,public_id,iso_code,name',
            'administrativeUnit:id,public_id,name',
        ])->loadCount(['homeChurches', 'memberships', 'firstTimers', 'homeChurchApplications']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function destroyChurch(Request $request, string $church, DeleteChurchAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $target = Church::query()->where('public_id', $church)->firstOrFail();
        $context->ensureContains($request, $target->scopeReference());
        $this->execute(function () use ($action, $target, $context, $request): true {
            $action->handle($target, $context->actor($request));

            return true;
        });

        return ApiResponse::success($request, ['id' => $church, 'deleted' => true]);
    }

    public function homeChurches(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->homeChurches($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function storeHomeChurch(CreateHomeChurchRequest $request, CreateHomeChurchAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $church = Church::query()->where('public_id', $request->validated('church_id'))->firstOrFail();
        $context->ensureContains($request, $church->scopeReference());
        $leader = Person::query()->where('public_id', $request->validated('leader_person_id'))->firstOrFail();
        $location = Location::query()->where('public_id', $request->validated('location_id'))->firstOrFail();
        $unit = AdministrativeUnit::query()->where('public_id', $request->validated('administrative_unit_id'))->firstOrFail();
        $context->ensureContains($request, new ScopeReference('administrative_unit', $unit->public_id));
        $homeChurch = $this->execute(fn (): HomeChurch => $action->handle(
            $church,
            $leader,
            $location,
            $unit,
            (string) $request->validated('name'),
            $context->actor($request),
        ));
        $homeChurch->load([
            'church:id,public_id,name',
            ...PersonDisplayName::eager('leader'),
        ]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($homeChurch))->resolve($request), status: 201);
    }

    public function showHomeChurch(Request $request, string $homeChurch, ProtectedAdminContext $context): JsonResponse
    {
        $target = HomeChurch::query()
            ->with([
                'church:id,public_id,name',
                'location:id,public_id,name',
                'administrativeUnit:id,public_id,name',
                ...PersonDisplayName::eager('leader'),
            ])
            ->withCount('memberships')
            ->where('public_id', $homeChurch)
            ->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    public function updateHomeChurch(Request $request, string $homeChurch, UpdateHomeChurchAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'leader_person_id' => ['required', 'ulid', 'exists:people,public_id'],
        ]);
        $target = HomeChurch::query()->with('church')->where('public_id', $homeChurch)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $leader = Person::query()->where('public_id', $data['leader_person_id'])->firstOrFail();
        $updated = $this->execute(fn (): HomeChurch => $action->handle($target, $data['name'], $leader, $context->actor($request)));
        $updated->load(['church:id,public_id,name', 'location:id,public_id,name', 'administrativeUnit:id,public_id,name', ...PersonDisplayName::eager('leader')])
            ->loadCount('memberships');

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function updateHomeChurchStatus(Request $request, string $homeChurch, UpdateHomeChurchStatusAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:active,suspended,closed'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $target = HomeChurch::query()->with('church')->where('public_id', $homeChurch)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $updated = $this->execute(fn (): HomeChurch => $action->handle(
            $target,
            HomeChurchStatus::from($data['status']),
            $data['reason'],
            $context->actor($request),
        ));
        $updated->load(['church:id,public_id,name', ...PersonDisplayName::eager('leader')])->loadCount('memberships');

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function applications(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->homeChurchApplications($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function showApplication(Request $request, string $application, ProtectedAdminContext $context): JsonResponse
    {
        $target = HomeChurchApplication::query()
            ->with([
                'church:id,public_id,name',
                'homeChurch:id,public_id,name',
                'location:id,public_id,name',
                'administrativeUnit:id,public_id,name',
                'transitions' => fn ($query) => $query->orderBy('occurred_at'),
                ...PersonDisplayName::eager('applicant'),
            ])
            ->where('public_id', $application)
            ->firstOrFail();
        $church = Church::query()->findOrFail($target->church_id);
        $context->ensureContains($request, $church->scopeReference());

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
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
            $request->validated('notes'),
            $request->validated('expected_status')
                ? HomeChurchApplicationStatus::from((string) $request->validated('expected_status'))
                : null,
        ));
        $updated->load([
            'church:id,public_id,name',
            'homeChurch:id,public_id,name',
            'location:id,public_id,name',
            'administrativeUnit:id,public_id,name',
            'transitions' => fn ($query) => $query->orderBy('occurred_at'),
            ...PersonDisplayName::eager('applicant'),
        ]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function firstTimers(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->firstTimers($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function memberships(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->memberships($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
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

    public function updateFirstTimer(UpdateFirstTimerRequest $request, string $firstTimer, UpdateFirstTimerAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $target = FirstTimer::query()->with('church')->where('public_id', $firstTimer)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $person = Person::query()->where('public_id', $request->validated('person_id'))->firstOrFail();
        $church = Church::query()->where('public_id', $request->validated('church_id'))->firstOrFail();
        $homeChurch = $request->validated('home_church_id') === null
            ? null
            : HomeChurch::query()->where('public_id', $request->validated('home_church_id'))->firstOrFail();
        $context->ensureContains($request, $church->scopeReference());
        $updated = $this->execute(fn (): FirstTimer => $action->handle(
            $target,
            $person,
            $church,
            $homeChurch,
            $request->validated('registered_at') === null ? null : CarbonImmutable::parse((string) $request->validated('registered_at')),
            $context->actor($request),
        ));
        $updated->load([...PersonDisplayName::eager(), 'church:id,public_id,name', 'homeChurch:id,public_id,name']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($updated))->resolve($request));
    }

    public function destroyFirstTimer(Request $request, string $firstTimer, DeleteFirstTimerAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $target = FirstTimer::query()->with('church')->where('public_id', $firstTimer)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $this->execute(function () use ($action, $target, $context, $request): true {
            $action->handle($target, $context->actor($request));

            return true;
        });

        return ApiResponse::success($request, ['id' => $firstTimer, 'deleted' => true]);
    }

    public function followUpTasks(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->followUpTasks($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function storeFollowUpTask(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'first_timer_id' => ['required', 'ulid', 'exists:first_timers,public_id'],
            'assigned_to_person_id' => ['nullable', 'ulid', 'exists:people,public_id'],
            'due_at' => ['nullable', 'date'],
        ]);
        $firstTimer = FirstTimer::query()->with('church')->where('public_id', $data['first_timer_id'])->firstOrFail();
        $context->ensureContains($request, $firstTimer->church->scopeReference());
        $assignee = isset($data['assigned_to_person_id'])
            ? Person::query()->where('public_id', $data['assigned_to_person_id'])->firstOrFail()
            : null;
        $task = $this->execute(function () use ($firstTimer, $assignee, $data): FollowUpTask {
            $record = new FollowUpTask([
                'first_timer_id' => $firstTimer->getKey(),
                'assigned_to_person_id' => $assignee?->getKey(),
                'type' => FollowUpTaskType::FirstTimerContact,
                'due_at' => isset($data['due_at']) ? CarbonImmutable::parse($data['due_at']) : now()->utc()->addDays(3),
            ]);
            $record->status = FollowUpTaskStatus::Pending;
            $record->save();

            return $record;
        });
        $task->load([
            'firstTimer.church:id,public_id,name',
            ...PersonDisplayName::eager('firstTimer.person'),
            ...PersonDisplayName::eager('assignedTo'),
        ]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($task))->resolve($request), status: 201);
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
        $schedules = $request->validated('meeting_schedules');
        $meetingDay = $request->validated('meeting_day')
            ?? (is_array($schedules) ? ($schedules[0]['day'] ?? MeetingDay::Sunday->value) : MeetingDay::Sunday->value);
        $meetingTime = $request->validated('meeting_time')
            ?? (is_array($schedules) ? ($schedules[0]['time'] ?? '18:00') : '18:00');
        $application = $this->execute(fn (): HomeChurchApplication => $action->handle(new HomeChurchApplicationData(
            applicant: Person::query()->where('public_id', $request->validated('applicant_person_id'))->firstOrFail(),
            church: $church,
            location: Location::query()->where('public_id', $request->validated('location_id'))->firstOrFail(),
            administrativeUnit: AdministrativeUnit::query()->where('public_id', $request->validated('administrative_unit_id'))->firstOrFail(),
            proposedName: (string) ($request->validated('proposed_name') ?? ''),
            expectedParticipants: (int) $request->validated('expected_participants'),
            meetingDay: MeetingDay::from((string) $meetingDay),
            meetingTime: (string) $meetingTime,
            contactEmail: (string) $request->validated('contact_email'),
            contactPhone: (string) $request->validated('contact_phone'),
            guidelinesAgreedAt: CarbonImmutable::parse((string) $request->validated('guidelines_agreed_at')),
            residenceFamilyName: $request->validated('residence_family_name'),
            meetingSchedules: is_array($schedules) ? $schedules : null,
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

    public function storePrayerRequest(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'subject' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $person = Person::query()->with(['memberships.church', 'firstTimers.church'])->where('public_id', $data['person_id'])->firstOrFail();
        $this->ensurePersonInChurchScope($request, $context, $person);
        $prayer = $this->execute(fn (): PrayerRequest => tap(new PrayerRequest, function (PrayerRequest $record) use ($person, $data): void {
            $record->forceFill([
                'person_id' => $person->getKey(),
                'subject' => $data['subject'],
                'body' => $data['body'],
                'status' => 'open',
            ])->save();
        }));
        $prayer->load(PersonDisplayName::eager());

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($prayer))->resolve($request), status: 201);
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

    public function storePastoralNeed(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'person_id' => ['nullable', 'ulid', 'exists:people,public_id'],
            'church_id' => ['nullable', 'ulid', 'exists:churches,public_id'],
            'home_church_id' => ['nullable', 'ulid', 'exists:home_churches,public_id'],
            'category' => ['required', 'string', 'max:100'],
            'summary' => ['required', 'string', 'max:2000'],
        ]);

        if (empty($data['person_id']) && empty($data['church_id']) && empty($data['home_church_id'])) {
            throw ValidationException::withMessages([
                'summary' => 'A need must be linked to a person, church, or home church.',
            ]);
        }

        if (! empty($data['home_church_id']) && ! empty($data['church_id'])) {
            throw ValidationException::withMessages([
                'home_church_id' => 'Link the need to either a church or a home church, not both.',
            ]);
        }

        $person = null;
        $churchId = null;
        $homeChurchId = null;

        if (! empty($data['home_church_id'])) {
            $homeChurch = HomeChurch::query()->with('church')->where('public_id', $data['home_church_id'])->firstOrFail();
            $context->ensureContains($request, $homeChurch->church->scopeReference());
            $homeChurchId = $homeChurch->getKey();
            $churchId = $homeChurch->church_id;
        } elseif (! empty($data['church_id'])) {
            $church = Church::query()->where('public_id', $data['church_id'])->firstOrFail();
            $context->ensureContains($request, $church->scopeReference());
            $churchId = $church->getKey();
        }

        if (! empty($data['person_id'])) {
            $person = Person::query()->where('public_id', $data['person_id'])->firstOrFail();
            $this->ensurePersonInChurchScope($request, $context, $person);
        }

        $need = $this->execute(fn (): PastoralNeed => tap(new PastoralNeed, function (PastoralNeed $record) use ($person, $churchId, $homeChurchId, $data): void {
            $record->forceFill([
                'person_id' => $person?->getKey(),
                'church_id' => $churchId,
                'home_church_id' => $homeChurchId,
                'category' => $data['category'],
                'summary' => $data['summary'],
                'status' => 'open',
            ])->save();
        }));
        $need->load([
            ...PersonDisplayName::eager(),
            'church:id,public_id,name',
            'homeChurch:id,public_id,name,church_id',
        ]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($need))->resolve($request), status: 201);
    }

    public function transitionPastoralNeed(TransitionPastoralRecordRequest $request, string $need, ProtectedAdminContext $context): JsonResponse
    {
        $status = (string) $request->validated('status');
        $this->assertPastoralStatus($status, ['open', 'approved', 'rejected', 'closed']);
        $target = PastoralNeed::query()
            ->with([
                'person.memberships.church',
                'person.firstTimers.church',
                'church',
                'homeChurch.church',
            ])
            ->where('public_id', $need)
            ->firstOrFail();
        $this->ensurePastoralNeedInScope($request, $context, $target);
        $target->forceFill(['status' => $status])->save();
        $target->load([
            ...PersonDisplayName::eager(),
            'church:id,public_id,name',
            'homeChurch:id,public_id,name,church_id',
        ]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    public function updateFollowUpTask(Request $request, string $task, ProtectedAdminContext $context): JsonResponse
    {
        $target = FollowUpTask::query()->with('firstTimer.church')->where('public_id', $task)->firstOrFail();
        $context->ensureContains($request, $target->firstTimer->church->scopeReference());
        if ($target->status === FollowUpTaskStatus::Completed) {
            abort(422, 'Completed follow-up tasks cannot be edited.');
        }
        $data = $request->validate([
            'assigned_to_person_id' => ['nullable', 'ulid', 'exists:people,public_id'],
            'due_at' => ['nullable', 'date'],
        ]);
        $this->execute(function () use ($target, $data): void {
            if (array_key_exists('assigned_to_person_id', $data)) {
                $target->assigned_to_person_id = $data['assigned_to_person_id'] === null
                    ? null
                    : Person::query()->where('public_id', $data['assigned_to_person_id'])->value('id');
            }
            if (array_key_exists('due_at', $data)) {
                $target->due_at = $data['due_at'] === null
                    ? null
                    : CarbonImmutable::parse($data['due_at']);
            }
            $target->save();
        });
        $target->load([
            'firstTimer.church:id,public_id,name',
            ...PersonDisplayName::eager('firstTimer.person'),
            ...PersonDisplayName::eager('assignedTo'),
        ]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    public function updatePrayerRequest(Request $request, string $prayer, ProtectedAdminContext $context): JsonResponse
    {
        $target = PrayerRequest::query()
            ->with(['person.memberships.church', 'person.firstTimers.church'])
            ->where('public_id', $prayer)
            ->firstOrFail();
        $this->ensurePersonInChurchScope($request, $context, $target->person);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', 'string', 'in:open,assigned,rejected,answered'],
        ]);
        if (isset($data['status'])) {
            $this->assertPastoralStatus($data['status'], ['open', 'assigned', 'rejected', 'answered']);
        }
        $this->execute(function () use ($target, $data): void {
            $target->forceFill([
                'subject' => $data['subject'],
                'body' => $data['body'],
                'status' => $data['status'] ?? $target->status,
            ])->save();
        });
        $target->load(PersonDisplayName::eager());

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    public function updatePastoralNeed(Request $request, string $need, ProtectedAdminContext $context): JsonResponse
    {
        $target = PastoralNeed::query()
            ->with([
                'person.memberships.church',
                'person.firstTimers.church',
                'church',
                'homeChurch.church',
            ])
            ->where('public_id', $need)
            ->firstOrFail();
        $this->ensurePastoralNeedInScope($request, $context, $target);
        $data = $request->validate([
            'person_id' => ['nullable', 'ulid', 'exists:people,public_id'],
            'church_id' => ['nullable', 'ulid', 'exists:churches,public_id'],
            'home_church_id' => ['nullable', 'ulid', 'exists:home_churches,public_id'],
            'category' => ['required', 'string', 'max:100'],
            'summary' => ['required', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'in:open,approved,rejected,closed'],
        ]);
        if (isset($data['status'])) {
            $this->assertPastoralStatus($data['status'], ['open', 'approved', 'rejected', 'closed']);
        }

        $person = null;
        $churchId = $target->church_id;
        $homeChurchId = $target->home_church_id;

        if (! empty($data['home_church_id'])) {
            $homeChurch = HomeChurch::query()->with('church')->where('public_id', $data['home_church_id'])->firstOrFail();
            $context->ensureContains($request, $homeChurch->church->scopeReference());
            $homeChurchId = $homeChurch->getKey();
            $churchId = $homeChurch->church_id;
        } elseif (! empty($data['church_id'])) {
            $church = Church::query()->where('public_id', $data['church_id'])->firstOrFail();
            $context->ensureContains($request, $church->scopeReference());
            $churchId = $church->getKey();
            $homeChurchId = null;
        } elseif (array_key_exists('home_church_id', $data) && $data['home_church_id'] === null && array_key_exists('church_id', $data) && $data['church_id'] === null) {
            $churchId = null;
            $homeChurchId = null;
        }

        if (! empty($data['person_id'])) {
            $person = Person::query()->where('public_id', $data['person_id'])->firstOrFail();
            $this->ensurePersonInChurchScope($request, $context, $person);
        } elseif (array_key_exists('person_id', $data) && $data['person_id'] === null) {
            $person = null;
        } else {
            $person = $target->person;
        }

        $this->execute(function () use ($target, $data, $person, $churchId, $homeChurchId): void {
            $target->forceFill([
                'person_id' => $person?->getKey(),
                'church_id' => $churchId,
                'home_church_id' => $homeChurchId,
                'category' => $data['category'],
                'summary' => $data['summary'],
                'status' => $data['status'] ?? $target->status,
            ])->save();
        });
        $target->load([
            ...PersonDisplayName::eager(),
            'church:id,public_id,name',
            'homeChurch:id,public_id,name,church_id',
        ]);

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

    private function ensurePastoralNeedInScope(Request $request, ProtectedAdminContext $context, PastoralNeed $need): void
    {
        if ($need->homeChurch !== null) {
            $context->ensureContains($request, $need->homeChurch->church->scopeReference());

            return;
        }

        if ($need->church !== null) {
            $context->ensureContains($request, $need->church->scopeReference());

            return;
        }

        $this->ensurePersonInChurchScope($request, $context, $need->person);
    }

    private function page(ListProtectedDomainRecordsRequest $request, LengthAwarePaginator $paginator): JsonResponse
    {
        return ApiResponse::success($request, ProtectedDomainRecordResource::collection($paginator->getCollection())->resolve($request), [
            'pagination' => ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ]);
    }
}
