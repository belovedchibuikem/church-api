<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\Actions\CreateMinistryEventAction;
use App\Events\Actions\DeleteMinistryEventAction;
use App\Events\Actions\RecordEventAttendanceAction;
use App\Events\Actions\RecordEventFeedbackAction;
use App\Events\Actions\RegisterForEventAction;
use App\Events\Actions\UpdateMinistryEventAction;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CreateMinistryEventRequest;
use App\Http\Requests\Api\V1\Admin\UpdateMinistryEventRequest;
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
use Illuminate\Http\Request;

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

    public function show(Request $request, string $event, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = MinistryEvent::query()
            ->with(['location:id,public_id,name'])
            ->withCount('registrations')
            ->where('public_id', $event)
            ->firstOrFail();
        $payload = (new ProtectedCatalogRecordResource($target))->resolve($request);
        $payload['registrations_count'] = $target->registrations_count;
        $payload['can_delete'] = $target->registrations_count === 0;

        return ApiResponse::success($request, $payload);
    }

    public function update(UpdateMinistryEventRequest $request, string $event, UpdateMinistryEventAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = MinistryEvent::query()->where('public_id', $event)->firstOrFail();
        $attributes = [];

        if ($request->has('location_id')) {
            $attributes['location'] = $request->validated('location_id') === null
                ? null
                : Location::query()->where('public_id', $request->validated('location_id'))->firstOrFail();
        }
        if ($request->has('category_code')) {
            $attributes['category_code'] = $request->validated('category_code');
        }
        if ($request->has('name')) {
            $attributes['name'] = $request->validated('name');
        }
        if ($request->has('starts_at')) {
            $attributes['starts_at'] = CarbonImmutable::parse((string) $request->validated('starts_at'));
        }
        if ($request->has('ends_at')) {
            $attributes['ends_at'] = CarbonImmutable::parse((string) $request->validated('ends_at'));
        }
        if ($request->has('registration_opens_at')) {
            $attributes['registration_opens_at'] = $request->validated('registration_opens_at') === null
                ? null
                : CarbonImmutable::parse((string) $request->validated('registration_opens_at'));
        }
        if ($request->has('registration_closes_at')) {
            $attributes['registration_closes_at'] = $request->validated('registration_closes_at') === null
                ? null
                : CarbonImmutable::parse((string) $request->validated('registration_closes_at'));
        }
        if ($request->has('fee_amount_minor')) {
            $attributes['fee_amount_minor'] = $request->validated('fee_amount_minor') === null
                ? null
                : (int) $request->validated('fee_amount_minor');
        }
        if ($request->has('fee_currency')) {
            $attributes['fee_currency'] = $request->validated('fee_currency');
        }
        if ($request->has('capacity')) {
            $attributes['capacity'] = $request->validated('capacity') === null
                ? null
                : (int) $request->validated('capacity');
        }
        if ($request->has('published_at')) {
            $attributes['published_at'] = $request->validated('published_at') === null
                ? null
                : CarbonImmutable::parse((string) $request->validated('published_at'));
        }

        $updated = $this->execute(fn (): MinistryEvent => $action->handle($target, $attributes, $context->actor($request)));
        $updated->load(['location:id,public_id,name']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function destroy(Request $request, string $event, DeleteMinistryEventAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = MinistryEvent::query()->where('public_id', $event)->firstOrFail();
        $this->execute(fn () => $action->handle($target, $context->actor($request)));

        return ApiResponse::success($request, ['id' => $event, 'deleted' => true]);
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
