<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Events\Actions\RecordEventFeedbackAction;
use App\Events\Actions\RegisterForEventAction;
use App\Files\Actions\ApproveFileAssetAction;
use App\Files\Actions\StoreFileAssetAction;
use App\Files\Data\StoreFileAssetData;
use App\Files\FileAssetClassification;
use App\Files\FileAssetStatus;
use App\Files\FileAssetStreamResponse;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\ListUserRecordsRequest;
use App\Http\Requests\Api\V1\User\UserRecordEventFeedbackRequest;
use App\Http\Requests\Api\V1\User\UserRegisterForEventRequest;
use App\Http\Requests\Api\V1\User\UserStoreFileAssetRequest;
use App\Http\Requests\Api\V1\User\UserSubmitDataSubjectRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Models\EventFeedback;
use App\Models\EventRegistration;
use App\Models\FileAsset;
use App\Models\MinistryEvent;
use App\Models\User;
use App\Privacy\Actions\SubmitDataSubjectRequestAction;
use App\Privacy\DataSubjectRequestType;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UserDomainOperationsController extends Controller
{
    use ExecutesDomainMutations;

    private const PROFILE_AVATAR_PURPOSE = 'profile.avatar';

    public function registerForEvent(UserRegisterForEventRequest $request, string $event, RegisterForEventAction $action): JsonResponse
    {
        $user = $this->actor($request);
        $person = $user->person;
        if ($person === null) {
            throw new UnprocessableEntityHttpException('The authenticated user is not linked to a person.');
        }
        $target = MinistryEvent::query()->where('public_id', $event)->firstOrFail();
        $registration = $this->execute(fn (): EventRegistration => $action->handle(
            $target,
            $person,
            (string) $request->validated('idempotency_key'),
            $user,
        ));
        $registration->load([
            'event:id,public_id,name,starts_at,ends_at,location_id,fee_amount_minor,fee_currency',
            'event.location:id,public_id,name,locality',
            'person:id,public_id',
        ]);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($registration))->resolve($request), status: 201);
    }

    public function listRegistrations(ListUserRecordsRequest $request): JsonResponse
    {
        $user = $this->actor($request);
        $person = $user->person;
        if ($person === null) {
            throw new UnprocessableEntityHttpException('The authenticated user is not linked to a person.');
        }

        $when = (string) $request->validated('filter.when', 'all');
        $now = now()->utc();
        $query = EventRegistration::query()
            ->with([
                'event:id,public_id,name,starts_at,ends_at,location_id,fee_amount_minor,fee_currency',
                'event.location:id,public_id,name,locality',
                'person:id,public_id',
            ])
            ->where('person_id', $person->getKey())
            ->whereHas('event', function ($eventQuery) use ($when, $now): void {
                if ($when === 'upcoming') {
                    $eventQuery->where('starts_at', '>=', $now);
                } elseif ($when === 'past') {
                    $eventQuery->where('starts_at', '<', $now);
                }
            })
            ->latest('registered_at');

        $paginator = $query->paginate((int) $request->validated('per_page', 25));

        return $this->page($request, $paginator);
    }

    public function showRegistration(Request $request, string $registration): JsonResponse
    {
        $user = $this->actor($request);
        $person = $user->person;
        if ($person === null) {
            throw new UnprocessableEntityHttpException('The authenticated user is not linked to a person.');
        }
        $owned = EventRegistration::query()
            ->where('public_id', $registration)
            ->where('person_id', $person->getKey())
            ->firstOrFail();
        $owned->load([
            'event:id,public_id,name,starts_at,ends_at,location_id,fee_amount_minor,fee_currency',
            'event.location:id,public_id,name,locality',
            'person:id,public_id',
        ]);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($owned))->resolve($request));
    }

    public function recordFeedback(UserRecordEventFeedbackRequest $request, RecordEventFeedbackAction $action): JsonResponse
    {
        $user = $this->actor($request);
        $registration = EventRegistration::query()->where('public_id', $request->validated('registration_id'))->firstOrFail();
        if ($user->person === null || (int) $registration->person_id !== (int) $user->person->getKey()) {
            throw new UnprocessableEntityHttpException('Feedback is limited to the authenticated person registration.');
        }
        $feedback = $this->execute(fn (): EventFeedback => $action->handle($registration, (int) $request->validated('rating'), $user));
        $feedback->load(['registration:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($feedback))->resolve($request), status: 201);
    }

    public function submitDataSubjectRequest(UserSubmitDataSubjectRequest $request, SubmitDataSubjectRequestAction $action): JsonResponse
    {
        $user = $this->actor($request);
        $person = $user->person;
        if ($person === null) {
            throw new UnprocessableEntityHttpException('The authenticated user is not linked to a person.');
        }
        $result = $this->execute(fn () => $action->handle(
            $person,
            DataSubjectRequestType::from((string) $request->validated('request_type')),
            (string) $request->validated('idempotency_key'),
            $request->validated('notes'),
            $user,
        ));
        $result->load(['person:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($result))->resolve($request), status: 201);
    }

    public function storeFile(
        UserStoreFileAssetRequest $request,
        StoreFileAssetAction $storeFile,
        ApproveFileAssetAction $approveFile,
    ): JsonResponse {
        $user = $this->actor($request);
        $person = $user->person;
        $purpose = (string) $request->validated('purpose');

        if ($purpose === self::PROFILE_AVATAR_PURPOSE && $person === null) {
            throw new UnprocessableEntityHttpException('The authenticated user is not linked to a person.');
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $asset = $this->execute(function () use (
            $request,
            $file,
            $storeFile,
            $approveFile,
            $user,
            $person,
            $purpose,
        ): FileAsset {
            $asset = $storeFile->handle(new StoreFileAssetData(
                file: $file,
                purpose: $purpose,
                classification: FileAssetClassification::from((string) $request->validated('classification')),
                idempotencyKey: (string) $request->validated('idempotency_key'),
                owner: $person,
                actor: $user,
            ));

            if ($purpose === self::PROFILE_AVATAR_PURPOSE && $asset->status !== FileAssetStatus::Rejected) {
                $asset = $approveFile->handle($asset, $user);
            }

            return $asset;
        });
        $asset->load(['owner:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($asset))->resolve($request), status: 201);
    }

    public function files(ListUserRecordsRequest $request): JsonResponse
    {
        $user = $this->actor($request);
        $person = $user->person;
        if ($person === null) {
            throw new UnprocessableEntityHttpException('The authenticated user is not linked to a person.');
        }
        $paginator = FileAsset::query()
            ->with(['owner:id,public_id'])
            ->where('owner_person_id', $person->getKey())
            ->whereNull('deleted_at')
            ->latest('created_at')
            ->paginate((int) $request->validated('per_page', 25));

        return $this->page($request, $paginator);
    }

    public function stream(Request $request, string $file, FileAssetStreamResponse $streams): StreamedResponse
    {
        $user = $this->actor($request);
        $person = $user->person;
        if ($person === null) {
            throw new UnprocessableEntityHttpException('The authenticated user is not linked to a person.');
        }
        $owned = FileAsset::query()
            ->available()
            ->where('public_id', $file)
            ->where('owner_person_id', $person->getKey())
            ->firstOrFail();

        return $streams->handle($owned, $request->boolean('download'));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    private function page(ListUserRecordsRequest $request, LengthAwarePaginator $paginator): JsonResponse
    {
        return ApiResponse::success($request, ProtectedCatalogRecordResource::collection($paginator->getCollection())->resolve($request), [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
