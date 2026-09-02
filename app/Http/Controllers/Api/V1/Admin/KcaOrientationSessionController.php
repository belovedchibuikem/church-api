<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CreateKcaOrientationSessionRequest;
use App\Http\Requests\Api\V1\Admin\UpdateKcaOrientationSessionRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Models\KcaCohort;
use App\Models\KcaOrientationSession;
use App\Models\Location;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Kca\CreateKcaOrientationSessionAction;
use App\Support\Kca\DeleteKcaOrientationSessionAction;
use App\Support\Kca\UpdateKcaOrientationSessionAction;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KcaOrientationSessionController extends Controller
{
    use ExecutesDomainMutations;

    public function store(
        CreateKcaOrientationSessionRequest $request,
        CreateKcaOrientationSessionAction $action,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $cohort = $request->validated('cohort_id') === null
            ? null
            : KcaCohort::query()->where('public_id', $request->validated('cohort_id'))->firstOrFail();
        $location = $request->validated('location_id') === null
            ? null
            : Location::query()->where('public_id', $request->validated('location_id'))->firstOrFail();

        $session = $this->execute(fn (): KcaOrientationSession => $action->handle([
            'cohort' => $cohort,
            'location' => $location,
            'name' => (string) $request->validated('name'),
            'venueLabel' => $request->validated('venue_label'),
            'startsAt' => CarbonImmutable::parse((string) $request->validated('starts_at')),
            'endsAt' => $request->validated('ends_at') === null
                ? null
                : CarbonImmutable::parse((string) $request->validated('ends_at')),
            'capacity' => $request->validated('capacity') === null ? null : (int) $request->validated('capacity'),
            'notes' => $request->validated('notes'),
            'publishedAt' => $request->validated('published_at') === null
                ? null
                : CarbonImmutable::parse((string) $request->validated('published_at')),
        ], $context->actor($request)));

        $session->load(['cohort:id,public_id,name', 'location:id,public_id,name']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($session))->resolve($request), status: 201);
    }

    public function show(Request $request, string $session, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = KcaOrientationSession::query()
            ->with([
                'cohort' => fn ($query) => $query->select('id', 'public_id', 'name')->withCount('enrollments'),
                'location:id,public_id,name',
            ])
            ->where('public_id', $session)
            ->firstOrFail();

        $payload = (new ProtectedCatalogRecordResource($target))->resolve($request);
        $payload['can_delete'] = true;

        return ApiResponse::success($request, $payload);
    }

    public function update(
        UpdateKcaOrientationSessionRequest $request,
        string $session,
        UpdateKcaOrientationSessionAction $action,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $target = KcaOrientationSession::query()->where('public_id', $session)->firstOrFail();
        $attributes = [];

        if ($request->has('cohort_id')) {
            $attributes['cohort'] = $request->validated('cohort_id') === null
                ? null
                : KcaCohort::query()->where('public_id', $request->validated('cohort_id'))->firstOrFail();
        }
        if ($request->has('location_id')) {
            $attributes['location'] = $request->validated('location_id') === null
                ? null
                : Location::query()->where('public_id', $request->validated('location_id'))->firstOrFail();
        }
        if ($request->has('name')) {
            $attributes['name'] = $request->validated('name');
        }
        if ($request->has('venue_label')) {
            $attributes['venue_label'] = $request->validated('venue_label');
        }
        if ($request->has('starts_at')) {
            $attributes['starts_at'] = CarbonImmutable::parse((string) $request->validated('starts_at'));
        }
        if ($request->has('ends_at')) {
            $attributes['ends_at'] = $request->validated('ends_at') === null
                ? null
                : CarbonImmutable::parse((string) $request->validated('ends_at'));
        }
        if ($request->has('capacity')) {
            $attributes['capacity'] = $request->validated('capacity') === null
                ? null
                : (int) $request->validated('capacity');
        }
        if ($request->has('notes')) {
            $attributes['notes'] = $request->validated('notes');
        }
        if ($request->has('published_at')) {
            $attributes['published_at'] = $request->validated('published_at') === null
                ? null
                : CarbonImmutable::parse((string) $request->validated('published_at'));
        }

        $updated = $this->execute(fn (): KcaOrientationSession => $action->handle($target, $attributes, $context->actor($request)));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function destroy(
        Request $request,
        string $session,
        DeleteKcaOrientationSessionAction $action,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $target = KcaOrientationSession::query()->where('public_id', $session)->firstOrFail();
        $this->execute(fn (): null => tap(null, fn () => $action->handle($target, $context->actor($request))));

        return ApiResponse::success($request, ['deleted' => true]);
    }
}
