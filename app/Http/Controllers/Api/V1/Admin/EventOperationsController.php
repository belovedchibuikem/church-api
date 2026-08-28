<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\Actions\CreateMinistryEventAction;
use App\Events\Actions\RecordEventAttendanceAction;
use App\Events\Actions\RecordEventFeedbackAction;
use App\Events\Actions\RegisterForEventAction;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CreateMinistryEventRequest;
use App\Http\Requests\Api\V1\Admin\RecordEventAttendanceRequest;
use App\Http\Requests\Api\V1\Admin\RecordEventFeedbackRequest;
use App\Http\Requests\Api\V1\Admin\RegisterForEventRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Models\EventAttendance;
use App\Models\EventFeedback;
use App\Models\EventRegistration;
use App\Models\Location;
use App\Models\MinistryEvent;
use App\Models\Person;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class EventOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function store(CreateMinistryEventRequest $request, CreateMinistryEventAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $location = $request->validated('location_id') === null
            ? null
            : Location::query()->where('public_id', $request->validated('location_id'))->firstOrFail();
        $event = $this->execute(fn (): MinistryEvent => $action->handle([
            'location' => $location,
            'categoryCode' => (string) $request->validated('category_code'),
            'name' => (string) $request->validated('name'),
            'startsAt' => CarbonImmutable::parse((string) $request->validated('starts_at')),
            'endsAt' => CarbonImmutable::parse((string) $request->validated('ends_at')),
            'registrationOpensAt' => $request->validated('registration_opens_at') === null
                ? null
                : CarbonImmutable::parse((string) $request->validated('registration_opens_at')),
            'registrationClosesAt' => $request->validated('registration_closes_at') === null
                ? null
                : CarbonImmutable::parse((string) $request->validated('registration_closes_at')),
            'feeAmountMinor' => $request->validated('fee_amount_minor') === null
                ? null
                : (int) $request->validated('fee_amount_minor'),
            'feeCurrency' => $request->validated('fee_currency'),
            'capacity' => $request->validated('capacity') === null ? null : (int) $request->validated('capacity'),
            'publishedAt' => $request->validated('published_at') === null
                ? null
                : CarbonImmutable::parse((string) $request->validated('published_at')),
        ], $context->actor($request)));
        $event->load(['location:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($event))->resolve($request), status: 201);
    }

    public function register(RegisterForEventRequest $request, string $event, RegisterForEventAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = MinistryEvent::query()->where('public_id', $event)->firstOrFail();
        $person = Person::query()->where('public_id', $request->validated('person_id'))->firstOrFail();
        $registration = $this->execute(fn (): EventRegistration => $action->handle(
            $target,
            $person,
            (string) $request->validated('idempotency_key'),
            $context->actor($request),
        ));
        $registration->load(['event:id,public_id', 'person:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($registration))->resolve($request), status: 201);
    }

    public function recordAttendance(RecordEventAttendanceRequest $request, string $registration, RecordEventAttendanceAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = EventRegistration::query()->where('public_id', $registration)->firstOrFail();
        $attendance = $this->execute(fn (): EventAttendance => $action->handle(
            $target,
            (string) $request->validated('source_code'),
            $context->actor($request),
        ));
        $attendance->load(['registration:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($attendance))->resolve($request), status: 201);
    }

    public function recordFeedback(RecordEventFeedbackRequest $request, string $registration, RecordEventFeedbackAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = EventRegistration::query()->where('public_id', $registration)->firstOrFail();
        $feedback = $this->execute(fn (): EventFeedback => $action->handle(
            $target,
            (int) $request->validated('rating'),
            $context->actor($request),
        ));
        $feedback->load(['registration:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($feedback))->resolve($request), status: 201);
    }
}
