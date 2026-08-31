<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\ListUserRecordsRequest;
use App\Http\Requests\Api\V1\User\StoreUserMissionInvitationRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedDomainRecordResource;
use App\Mission\Actions\CreateMissionInvitationAction;
use App\Models\Crusade;
use App\Models\Location;
use App\Models\MissionInvitation;
use App\Models\MissionSupportRequest;
use App\Models\Person;
use App\Models\User;
use App\Support\Api\ApiResponse;
use DomainException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UserMissionController extends Controller
{
    public function invitations(ListUserRecordsRequest $request): JsonResponse
    {
        $person = $this->requirePerson($this->actor($request));
        $paginator = MissionInvitation::query()
            ->with(['crusade:id,public_id,name', 'requestedLocation:id,public_id,name'])
            ->where('requester_person_id', $person->getKey())
            ->latest('status_changed_at')
            ->paginate((int) $request->validated('per_page', 25));

        return ApiResponse::success($request, ProtectedDomainRecordResource::collection($paginator->getCollection())->resolve($request), [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function storeInvitation(StoreUserMissionInvitationRequest $request, CreateMissionInvitationAction $action): JsonResponse
    {
        $person = $this->requirePerson($this->actor($request));
        $location = $request->validated('requested_location_id') === null
            ? null
            : Location::query()->where('public_id', $request->validated('requested_location_id'))->firstOrFail();
        $invitation = $this->execute(fn (): MissionInvitation => $action->handle(
            null,
            $person,
            $location,
            $this->actor($request),
            [
                'purpose' => $request->validated('purpose') ?? $request->validated('details'),
                'expected_attendance' => $request->validated('expected_attendance'),
                'notes' => $request->validated('details'),
                'idempotency_key' => $request->validated('idempotency_key') ?? $request->header('Idempotency-Key'),
                'application_data' => [
                    'title' => $request->validated('title'),
                    'type' => $request->validated('type'),
                    'start' => $request->validated('start'),
                    'location' => $request->validated('location'),
                    'details' => $request->validated('details'),
                ],
            ],
        ));
        $invitation->load(['crusade:id,public_id,name', 'requester:id,public_id', 'requestedLocation:id,public_id,name']);
        $invitation->refresh();

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($invitation))->resolve($request), status: 201);
    }

    public function showInvitation(Request $request, string $invitation): JsonResponse
    {
        $person = $this->requirePerson($this->actor($request));
        $record = MissionInvitation::query()
            ->with(['crusade:id,public_id,name', 'requestedLocation:id,public_id,name'])
            ->where('public_id', $invitation)
            ->where('requester_person_id', $person->getKey())
            ->firstOrFail();

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($record))->resolve($request));
    }

    public function storeSupportRequest(Request $request): JsonResponse
    {
        $person = $this->requirePerson($this->actor($request));
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'category' => ['nullable', 'string', 'max:80'],
            'details' => ['nullable', 'string', 'max:10000'],
            'amount_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'crusade_id' => ['nullable', 'ulid', 'exists:crusades,public_id'],
            'idempotency_key' => ['nullable', 'string', 'min:8', 'max:191'],
        ]);
        $hash = isset($data['idempotency_key'])
            ? hash_hmac('sha256', 'mission.support|'.$person->getKey().'|'.$data['idempotency_key'], (string) config('app.key'))
            : null;
        if ($hash !== null) {
            $existing = MissionSupportRequest::query()->where('idempotency_key_hash', $hash)->first();
            if ($existing !== null) {
                return ApiResponse::success($request, $this->supportPayload($existing), status: 201);
            }
        }
        $crusade = isset($data['crusade_id']) ? Crusade::query()->where('public_id', $data['crusade_id'])->first() : null;
        $item = MissionSupportRequest::query()->create([
            'requested_by_person_id' => $person->getKey(),
            'crusade_id' => $crusade?->getKey(),
            'title' => $data['title'],
            'category' => $data['category'] ?? 'general',
            'details' => $data['details'] ?? null,
            'amount_minor' => $data['amount_minor'] ?? null,
            'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null,
            'status' => 'submitted',
            'idempotency_key_hash' => $hash,
        ]);

        return ApiResponse::success($request, $this->supportPayload($item), status: 201);
    }

    public function supportRequests(ListUserRecordsRequest $request): JsonResponse
    {
        $person = $this->requirePerson($this->actor($request));
        $paginator = MissionSupportRequest::query()
            ->where('requested_by_person_id', $person->getKey())
            ->latest()
            ->paginate((int) $request->validated('per_page', 25));

        return ApiResponse::success($request, $paginator->getCollection()->map(fn (MissionSupportRequest $item): array => $this->supportPayload($item))->all(), [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function supportPayload(MissionSupportRequest $item): array
    {
        return [
            'id' => $item->public_id,
            'title' => $item->title,
            'category' => $item->category,
            'status' => $item->status,
            'amount_minor' => $item->amount_minor,
            'currency' => $item->currency,
        ];
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
        } catch (InvalidArgumentException|LogicException|DomainException $exception) {
            throw new UnprocessableEntityHttpException(previous: $exception);
        }
    }
}
