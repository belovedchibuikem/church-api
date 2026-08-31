<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Church\ChurchMembershipStatus;
use App\Church\MembershipJoinIntent;
use App\Exceptions\MembershipTransferRequiredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\JoinUserHomeChurchMembershipRequest;
use App\Http\Requests\Api\V1\User\ListUserRecordsRequest;
use App\Http\Requests\Api\V1\User\StartUserChurchMembershipRequest;
use App\Http\Requests\Api\V1\User\SubmitHomeChurchReportRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedDomainRecordResource;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\HomeChurch;
use App\Models\Person;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\Church\StartChurchMembershipAction;
use App\Support\Identity\PersonDisplayName;
use DomainException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UserChurchOperationsController extends Controller
{
    public function startMembership(
        StartUserChurchMembershipRequest $request,
        string $church,
        StartChurchMembershipAction $action,
    ): JsonResponse {
        $user = $this->actor($request);
        $person = $this->requirePerson($user);
        $target = Church::query()->where('public_id', $church)->firstOrFail();
        $homeChurch = $request->validated('home_church_id') === null
            ? null
            : HomeChurch::query()->where('public_id', $request->validated('home_church_id'))->firstOrFail();
        $membership = $this->execute(fn (): ChurchMembership => $action->handle(
            $person,
            $target,
            $homeChurch,
            null,
            $user,
            $homeChurch === null ? MembershipJoinIntent::Conventional : MembershipJoinIntent::HomeChurch,
            (bool) $request->boolean('confirm_transfer'),
        ));
        $membership->load(['person:id,public_id', 'church:id,public_id,name', 'homeChurch:id,public_id,name']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($membership))->resolve($request), status: 201);
    }

    public function joinHomeChurch(
        JoinUserHomeChurchMembershipRequest $request,
        string $homeChurch,
        StartChurchMembershipAction $action,
    ): JsonResponse {
        $user = $this->actor($request);
        $person = $this->requirePerson($user);
        $target = HomeChurch::query()->where('public_id', $homeChurch)->firstOrFail();
        $parent = Church::query()->findOrFail($target->church_id);
        $membership = $this->execute(fn (): ChurchMembership => $action->handle(
            $person,
            $parent,
            $target,
            null,
            $user,
            MembershipJoinIntent::HomeChurch,
            (bool) $request->boolean('confirm_transfer'),
        ));
        $membership->load(['person:id,public_id', 'church:id,public_id,name', 'homeChurch:id,public_id,name']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($membership))->resolve($request), status: 201);
    }

    public function memberships(ListUserRecordsRequest $request): JsonResponse
    {
        $person = $this->requirePerson($this->actor($request));
        $paginator = ChurchMembership::query()
            ->with(['person:id,public_id', 'church:id,public_id,name', 'homeChurch:id,public_id,name'])
            ->where('person_id', $person->getKey())
            ->latest('joined_at')
            ->paginate((int) $request->validated('per_page', 25));

        return $this->page($request, $paginator);
    }

    public function homeChurches(ListUserRecordsRequest $request): JsonResponse
    {
        $person = $this->requirePerson($this->actor($request));
        $membershipHomeIds = ChurchMembership::query()
            ->where('person_id', $person->getKey())
            ->whereNotNull('home_church_id')
            ->pluck('home_church_id');

        $ledHomeIds = HomeChurch::query()
            ->where('leader_person_id', $person->getKey())
            ->pluck('id');

        $paginator = HomeChurch::query()
            ->with(['church:id,public_id,name', 'leader:id,public_id'])
            ->whereIn('id', $membershipHomeIds->merge($ledHomeIds)->unique()->filter()->values())
            ->orderBy('name')
            ->paginate((int) $request->validated('per_page', 25));

        $rows = $paginator->getCollection()->map(static function (HomeChurch $home): array {
            return [
                'id' => $home->public_id,
                'name' => $home->name,
                'status' => $home->status?->value ?? (string) $home->status,
                'church_id' => $home->church?->public_id,
                'church_name' => $home->church?->name,
                'leader_person_id' => $home->leader?->public_id,
                'membership_status' => null,
            ];
        })->values()->all();

        return ApiResponse::success($request, $rows, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function churchMembers(ListUserRecordsRequest $request, string $church): JsonResponse
    {
        $caller = $this->requirePerson($this->actor($request));
        $target = Church::query()->where('public_id', $church)->firstOrFail();

        $callerMembership = ChurchMembership::query()
            ->where('person_id', $caller->getKey())
            ->where('church_id', $target->getKey())
            ->where('status', ChurchMembershipStatus::Active)
            ->first();

        if ($callerMembership === null) {
            abort(404);
        }

        $paginator = ChurchMembership::query()
            ->with([
                'person:id,public_id',
                'person.profile:id,person_id,given_name,middle_name,family_name,preferred_name,country',
                'person.user:id,person_id,name,email',
            ])
            ->where('church_id', $target->getKey())
            ->where('status', ChurchMembershipStatus::Active)
            ->latest('joined_at')
            ->paginate((int) $request->validated('per_page', 25));

        $rows = $paginator->getCollection()->map(static fn (ChurchMembership $membership): array => [
            'id' => $membership->public_id,
            'person_id' => $membership->person?->public_id,
            'person_name' => PersonDisplayName::of($membership->person),
            'status' => $membership->status->value,
            'joined_at' => $membership->joined_at?->utc()->toIso8601String(),
            'is_leader' => false,
        ])->values()->all();

        return ApiResponse::success($request, $rows, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function showHomeChurch(Request $request, string $homeChurch): JsonResponse
    {
        $person = $this->requirePerson($this->actor($request));
        [$target, $membership] = $this->accessibleHomeChurch($person, $homeChurch);
        $target->load(['church:id,public_id,name', 'leader:id,public_id']);

        $payload = (new ProtectedDomainRecordResource($target))->resolve($request);
        $payload['church_name'] = $target->church?->name;
        $payload['membership_status'] = $membership?->status->value;

        return ApiResponse::success($request, $payload);
    }

    public function storeHomeChurchReport(
        SubmitHomeChurchReportRequest $request,
        string $homeChurch,
        RecordAuditEventAction $recordAuditEvent,
    ): JsonResponse {
        $user = $this->actor($request);
        $person = $this->requirePerson($user);
        [$target, $membership] = $this->accessibleHomeChurch($person, $homeChurch);
        $summary = (string) $request->validated('summary');
        $audit = $this->execute(fn () => $recordAuditEvent->handle(new AuditEventData(
            action: 'home_church.report.submitted',
            actor: $user,
            targetType: 'home_church',
            targetId: $target->public_id,
            scopeType: 'home_church',
            scopeId: $target->public_id,
            metadata: ['summary' => $summary],
        )));
        $submittedAt = $audit->occurred_at?->utc()->toIso8601String() ?? now()->utc()->toIso8601String();

        return ApiResponse::success($request, [
            'id' => $membership?->public_id ?? $target->public_id,
            'status' => 'submitted',
            'submitted_at' => $submittedAt,
        ], status: 201);
    }

    /**
     * @return array{0: HomeChurch, 1: ChurchMembership|null}
     */
    private function accessibleHomeChurch(Person $person, string $homeChurch): array
    {
        $target = HomeChurch::query()->where('public_id', $homeChurch)->firstOrFail();
        $membership = ChurchMembership::query()
            ->where('person_id', $person->getKey())
            ->where('home_church_id', $target->getKey())
            ->where('status', ChurchMembershipStatus::Active)
            ->first();
        $isLeader = $target->leader_person_id !== null
            && (int) $target->leader_person_id === (int) $person->getKey();

        if ($membership === null && ! $isLeader) {
            abort(404);
        }

        return [$target, $membership];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    private function requirePerson(User $user): Person
    {
        $person = $user->person;
        if ($person === null) {
            throw new UnprocessableEntityHttpException('The authenticated user is not linked to a person.');
        }

        return $person;
    }

    private function execute(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (MembershipTransferRequiredException $exception) {
            throw $exception;
        } catch (InvalidArgumentException|LogicException|DomainException $exception) {
            throw new UnprocessableEntityHttpException(previous: $exception);
        }
    }

    private function page(ListUserRecordsRequest $request, LengthAwarePaginator $paginator): JsonResponse
    {
        return ApiResponse::success($request, ProtectedDomainRecordResource::collection($paginator->getCollection())->resolve($request), [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
