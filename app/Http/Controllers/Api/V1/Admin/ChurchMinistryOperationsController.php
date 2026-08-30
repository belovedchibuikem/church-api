<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListProtectedDomainRecordsRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedDomainRecordResource;
use App\Models\Church;
use App\Models\ChurchAnnouncement;
use App\Models\ChurchDepartment;
use App\Models\ChurchGroup;
use App\Models\ChurchRoleAssignment;
use App\Models\Convert;
use App\Models\CounsellingCase;
use App\Models\EvangelismActivity;
use App\Models\HomeChurch;
use App\Models\HomeChurchAttendanceRecord;
use App\Models\Person;
use App\Models\SafeguardingIncident;
use App\Models\Testimony;
use App\Queries\Admin\ProtectedDomainCatalogQuery;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\ScopeReference;
use App\Support\Identity\PersonDisplayName;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChurchMinistryOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function people(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->people($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function converts(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->converts($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function storeConvert(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'home_church_id' => ['nullable', 'ulid', 'exists:home_churches,public_id'],
            'converted_at' => ['nullable', 'date'],
            'baptized_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $church = Church::query()->where('public_id', $data['church_id'])->firstOrFail();
        $context->ensureContains($request, $church->scopeReference());
        $convert = $this->execute(function () use ($data, $church): Convert {
            return Convert::query()->create([
                'person_id' => Person::query()->where('public_id', $data['person_id'])->firstOrFail()->getKey(),
                'church_id' => $church->getKey(),
                'home_church_id' => isset($data['home_church_id'])
                    ? HomeChurch::query()->where('public_id', $data['home_church_id'])->value('id')
                    : null,
                'converted_at' => isset($data['converted_at']) ? CarbonImmutable::parse($data['converted_at']) : now()->utc(),
                'baptized_at' => isset($data['baptized_at']) ? CarbonImmutable::parse($data['baptized_at']) : null,
                'source' => $data['source'] ?? null,
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? null,
            ]);
        });
        $convert->load([...PersonDisplayName::eager(), 'church:id,public_id,name', 'homeChurch:id,public_id,name']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($convert))->resolve($request), status: 201);
    }

    public function updateConvert(Request $request, string $convert, ProtectedAdminContext $context): JsonResponse
    {
        $target = Convert::query()->with('church')->where('public_id', $convert)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $data = $request->validate([
            'person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'home_church_id' => ['nullable', 'ulid', 'exists:home_churches,public_id'],
            'converted_at' => ['nullable', 'date'],
            'baptized_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $church = Church::query()->where('public_id', $data['church_id'])->firstOrFail();
        $context->ensureContains($request, $church->scopeReference());
        $this->execute(function () use ($target, $data, $church): void {
            $target->forceFill([
                'person_id' => Person::query()->where('public_id', $data['person_id'])->firstOrFail()->getKey(),
                'church_id' => $church->getKey(),
                'home_church_id' => isset($data['home_church_id'])
                    ? HomeChurch::query()->where('public_id', $data['home_church_id'])->value('id')
                    : null,
                'converted_at' => isset($data['converted_at']) ? CarbonImmutable::parse($data['converted_at']) : $target->converted_at,
                'baptized_at' => array_key_exists('baptized_at', $data)
                    ? ($data['baptized_at'] ? CarbonImmutable::parse($data['baptized_at']) : null)
                    : $target->baptized_at,
                'source' => $data['source'] ?? $target->source,
                'status' => $data['status'] ?? $target->status,
                'notes' => $data['notes'] ?? $target->notes,
            ])->save();
        });
        $target->load([...PersonDisplayName::eager(), 'church:id,public_id,name', 'homeChurch:id,public_id,name']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    public function destroyConvert(Request $request, string $convert, ProtectedAdminContext $context): JsonResponse
    {
        $target = Convert::query()->with('church')->where('public_id', $convert)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $this->execute(fn () => $target->delete());

        return ApiResponse::success($request, ['id' => $convert, 'deleted' => true]);
    }

    public function evangelismActivities(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->evangelismActivities($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function storeEvangelismActivity(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'title' => ['required', 'string', 'max:191'],
            'activity_type' => ['nullable', 'string', 'max:80'],
            'souls_reached' => ['nullable', 'integer', 'min:0'],
            'decisions' => ['nullable', 'integer', 'min:0'],
            'occurred_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $church = Church::query()->where('public_id', $data['church_id'])->firstOrFail();
        $context->ensureContains($request, $church->scopeReference());
        $activity = $this->execute(fn (): EvangelismActivity => EvangelismActivity::query()->create([
            'church_id' => $church->getKey(),
            'title' => $data['title'],
            'activity_type' => $data['activity_type'] ?? 'outreach',
            'souls_reached' => $data['souls_reached'] ?? 0,
            'decisions' => $data['decisions'] ?? 0,
            'occurred_at' => isset($data['occurred_at']) ? CarbonImmutable::parse($data['occurred_at']) : now()->utc(),
            'status' => $data['status'] ?? 'completed',
            'notes' => $data['notes'] ?? null,
        ]));
        $activity->load(['church:id,public_id,name']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($activity))->resolve($request), status: 201);
    }

    public function updateEvangelismActivity(Request $request, string $activity, ProtectedAdminContext $context): JsonResponse
    {
        $target = EvangelismActivity::query()->with('church')->where('public_id', $activity)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $data = $request->validate([
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'title' => ['required', 'string', 'max:191'],
            'activity_type' => ['nullable', 'string', 'max:80'],
            'souls_reached' => ['nullable', 'integer', 'min:0'],
            'decisions' => ['nullable', 'integer', 'min:0'],
            'occurred_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $church = Church::query()->where('public_id', $data['church_id'])->firstOrFail();
        $this->execute(function () use ($target, $data, $church): void {
            $target->forceFill([
                'church_id' => $church->getKey(),
                'title' => $data['title'],
                'activity_type' => $data['activity_type'] ?? $target->activity_type,
                'souls_reached' => $data['souls_reached'] ?? $target->souls_reached,
                'decisions' => $data['decisions'] ?? $target->decisions,
                'occurred_at' => isset($data['occurred_at']) ? CarbonImmutable::parse($data['occurred_at']) : $target->occurred_at,
                'status' => $data['status'] ?? $target->status,
                'notes' => $data['notes'] ?? $target->notes,
            ])->save();
        });
        $target->load(['church:id,public_id,name']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    public function destroyEvangelismActivity(Request $request, string $activity, ProtectedAdminContext $context): JsonResponse
    {
        $target = EvangelismActivity::query()->with('church')->where('public_id', $activity)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $this->execute(fn () => $target->delete());

        return ApiResponse::success($request, ['id' => $activity, 'deleted' => true]);
    }

    public function departments(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->departments($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function storeDepartment(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'leader_person_id' => ['nullable', 'ulid', 'exists:people,public_id'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);
        $church = Church::query()->where('public_id', $data['church_id'])->firstOrFail();
        $context->ensureContains($request, $church->scopeReference());
        $department = $this->execute(fn (): ChurchDepartment => ChurchDepartment::query()->create([
            'church_id' => $church->getKey(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'leader_person_id' => isset($data['leader_person_id'])
                ? Person::query()->where('public_id', $data['leader_person_id'])->value('id')
                : null,
            'status' => $data['status'] ?? 'active',
        ]));
        $department->load(['church:id,public_id,name', ...PersonDisplayName::eager('leader')]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($department))->resolve($request), status: 201);
    }

    public function updateDepartment(Request $request, string $department, ProtectedAdminContext $context): JsonResponse
    {
        $target = ChurchDepartment::query()->with('church')->where('public_id', $department)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $data = $request->validate([
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'leader_person_id' => ['nullable', 'ulid', 'exists:people,public_id'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);
        $church = Church::query()->where('public_id', $data['church_id'])->firstOrFail();
        $this->execute(function () use ($target, $data, $church): void {
            $target->forceFill([
                'church_id' => $church->getKey(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'leader_person_id' => isset($data['leader_person_id'])
                    ? Person::query()->where('public_id', $data['leader_person_id'])->value('id')
                    : null,
                'status' => $data['status'] ?? $target->status,
            ])->save();
        });
        $target->load(['church:id,public_id,name', ...PersonDisplayName::eager('leader')]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    public function destroyDepartment(Request $request, string $department, ProtectedAdminContext $context): JsonResponse
    {
        $target = ChurchDepartment::query()->with('church')->where('public_id', $department)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $this->execute(fn () => $target->delete());

        return ApiResponse::success($request, ['id' => $department, 'deleted' => true]);
    }

    public function workers(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->roleAssignments($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25), 'worker'));
    }

    public function leaders(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->roleAssignments($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25), 'leader'));
    }

    public function disciples(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->roleAssignments($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25), 'disciple'));
    }

    public function storeRoleAssignment(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'department_id' => ['nullable', 'ulid', 'exists:church_departments,public_id'],
            'role_type' => ['required', 'in:worker,leader,disciple'],
            'title' => ['required', 'string', 'max:191'],
            'status' => ['nullable', 'string', 'max:40'],
            'started_at' => ['nullable', 'date'],
        ]);
        $church = Church::query()->where('public_id', $data['church_id'])->firstOrFail();
        $context->ensureContains($request, $church->scopeReference());
        $assignment = $this->execute(fn (): ChurchRoleAssignment => ChurchRoleAssignment::query()->create([
            'church_id' => $church->getKey(),
            'person_id' => Person::query()->where('public_id', $data['person_id'])->firstOrFail()->getKey(),
            'department_id' => isset($data['department_id'])
                ? ChurchDepartment::query()->where('public_id', $data['department_id'])->value('id')
                : null,
            'role_type' => $data['role_type'],
            'title' => $data['title'],
            'status' => $data['status'] ?? 'active',
            'started_at' => isset($data['started_at']) ? CarbonImmutable::parse($data['started_at']) : now()->utc(),
        ]));
        $assignment->load(['church:id,public_id,name', 'department:id,public_id,name', ...PersonDisplayName::eager()]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($assignment))->resolve($request), status: 201);
    }

    public function updateRoleAssignment(Request $request, string $assignment, ProtectedAdminContext $context): JsonResponse
    {
        $target = ChurchRoleAssignment::query()->with('church')->where('public_id', $assignment)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $data = $request->validate([
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'department_id' => ['nullable', 'ulid', 'exists:church_departments,public_id'],
            'role_type' => ['required', 'in:worker,leader,disciple'],
            'title' => ['required', 'string', 'max:191'],
            'status' => ['nullable', 'string', 'max:40'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
        ]);
        $church = Church::query()->where('public_id', $data['church_id'])->firstOrFail();
        $this->execute(function () use ($target, $data, $church): void {
            $target->forceFill([
                'church_id' => $church->getKey(),
                'person_id' => Person::query()->where('public_id', $data['person_id'])->firstOrFail()->getKey(),
                'department_id' => isset($data['department_id'])
                    ? ChurchDepartment::query()->where('public_id', $data['department_id'])->value('id')
                    : null,
                'role_type' => $data['role_type'],
                'title' => $data['title'],
                'status' => $data['status'] ?? $target->status,
                'started_at' => isset($data['started_at']) ? CarbonImmutable::parse($data['started_at']) : $target->started_at,
                'ended_at' => array_key_exists('ended_at', $data)
                    ? ($data['ended_at'] ? CarbonImmutable::parse($data['ended_at']) : null)
                    : $target->ended_at,
            ])->save();
        });
        $target->load(['church:id,public_id,name', 'department:id,public_id,name', ...PersonDisplayName::eager()]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    public function destroyRoleAssignment(Request $request, string $assignment, ProtectedAdminContext $context): JsonResponse
    {
        $target = ChurchRoleAssignment::query()->with('church')->where('public_id', $assignment)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $this->execute(fn () => $target->delete());

        return ApiResponse::success($request, ['id' => $assignment, 'deleted' => true]);
    }

    public function counsellingCases(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->counsellingCases($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function storeCounsellingCase(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'client_person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'counselor_person_id' => ['nullable', 'ulid', 'exists:people,public_id'],
            'case_type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:40'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'opened_at' => ['nullable', 'date'],
        ]);
        $church = Church::query()->where('public_id', $data['church_id'])->firstOrFail();
        $context->ensureContains($request, $church->scopeReference());
        $case = $this->execute(fn (): CounsellingCase => CounsellingCase::query()->create([
            'church_id' => $church->getKey(),
            'client_person_id' => Person::query()->where('public_id', $data['client_person_id'])->firstOrFail()->getKey(),
            'counselor_person_id' => isset($data['counselor_person_id'])
                ? Person::query()->where('public_id', $data['counselor_person_id'])->value('id')
                : null,
            'case_type' => $data['case_type'] ?? 'general',
            'status' => $data['status'] ?? 'open',
            'summary' => $data['summary'] ?? null,
            'opened_at' => isset($data['opened_at']) ? CarbonImmutable::parse($data['opened_at']) : now()->utc(),
        ]));
        $case->load(['church:id,public_id,name', ...PersonDisplayName::eager('client'), ...PersonDisplayName::eager('counselor')]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($case))->resolve($request), status: 201);
    }

    public function updateCounsellingCase(Request $request, string $case, ProtectedAdminContext $context): JsonResponse
    {
        $target = CounsellingCase::query()->with('church')->where('public_id', $case)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $data = $request->validate([
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'client_person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'counselor_person_id' => ['nullable', 'ulid', 'exists:people,public_id'],
            'case_type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:40'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'opened_at' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date'],
        ]);
        $church = Church::query()->where('public_id', $data['church_id'])->firstOrFail();
        $this->execute(function () use ($target, $data, $church): void {
            $target->forceFill([
                'church_id' => $church->getKey(),
                'client_person_id' => Person::query()->where('public_id', $data['client_person_id'])->firstOrFail()->getKey(),
                'counselor_person_id' => isset($data['counselor_person_id'])
                    ? Person::query()->where('public_id', $data['counselor_person_id'])->value('id')
                    : null,
                'case_type' => $data['case_type'] ?? $target->case_type,
                'status' => $data['status'] ?? $target->status,
                'summary' => $data['summary'] ?? $target->summary,
                'opened_at' => isset($data['opened_at']) ? CarbonImmutable::parse($data['opened_at']) : $target->opened_at,
                'closed_at' => array_key_exists('closed_at', $data)
                    ? ($data['closed_at'] ? CarbonImmutable::parse($data['closed_at']) : null)
                    : $target->closed_at,
            ])->save();
        });
        $target->load(['church:id,public_id,name', ...PersonDisplayName::eager('client'), ...PersonDisplayName::eager('counselor')]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    public function destroyCounsellingCase(Request $request, string $case, ProtectedAdminContext $context): JsonResponse
    {
        $target = CounsellingCase::query()->with('church')->where('public_id', $case)->firstOrFail();
        $context->ensureContains($request, $target->church->scopeReference());
        $this->execute(fn () => $target->delete());

        return ApiResponse::success($request, ['id' => $case, 'deleted' => true]);
    }

    public function testimonies(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->testimonies($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function storeTestimony(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'church_id' => ['nullable', 'ulid', 'exists:churches,public_id'],
            'title' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:10000'],
            'status' => ['nullable', 'string', 'max:40'],
            'submitted_at' => ['nullable', 'date'],
            'published_at' => ['nullable', 'date'],
        ]);
        $church = isset($data['church_id']) ? Church::query()->where('public_id', $data['church_id'])->firstOrFail() : null;
        if ($church !== null) {
            $context->ensureContains($request, $church->scopeReference());
        }
        $testimony = $this->execute(fn (): Testimony => Testimony::query()->create([
            'person_id' => Person::query()->where('public_id', $data['person_id'])->firstOrFail()->getKey(),
            'church_id' => $church?->getKey(),
            'title' => $data['title'],
            'body' => $data['body'],
            'status' => $data['status'] ?? 'pending',
            'submitted_at' => isset($data['submitted_at']) ? CarbonImmutable::parse($data['submitted_at']) : now()->utc(),
            'published_at' => isset($data['published_at']) ? CarbonImmutable::parse($data['published_at']) : null,
        ]));
        $testimony->load(['church:id,public_id,name', ...PersonDisplayName::eager()]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($testimony))->resolve($request), status: 201);
    }

    public function updateTestimony(Request $request, string $testimony, ProtectedAdminContext $context): JsonResponse
    {
        $target = Testimony::query()->with('church')->where('public_id', $testimony)->firstOrFail();
        if ($target->church !== null) {
            $context->ensureContains($request, $target->church->scopeReference());
        }
        $data = $request->validate([
            'person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'church_id' => ['nullable', 'ulid', 'exists:churches,public_id'],
            'title' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:10000'],
            'status' => ['nullable', 'string', 'max:40'],
            'submitted_at' => ['nullable', 'date'],
            'published_at' => ['nullable', 'date'],
        ]);
        $church = isset($data['church_id']) ? Church::query()->where('public_id', $data['church_id'])->firstOrFail() : null;
        $this->execute(function () use ($target, $data, $church): void {
            $target->forceFill([
                'person_id' => Person::query()->where('public_id', $data['person_id'])->firstOrFail()->getKey(),
                'church_id' => $church?->getKey(),
                'title' => $data['title'],
                'body' => $data['body'],
                'status' => $data['status'] ?? $target->status,
                'submitted_at' => isset($data['submitted_at']) ? CarbonImmutable::parse($data['submitted_at']) : $target->submitted_at,
                'published_at' => array_key_exists('published_at', $data)
                    ? ($data['published_at'] ? CarbonImmutable::parse($data['published_at']) : null)
                    : $target->published_at,
            ])->save();
        });
        $target->load(['church:id,public_id,name', ...PersonDisplayName::eager()]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    public function destroyTestimony(Request $request, string $testimony, ProtectedAdminContext $context): JsonResponse
    {
        $target = Testimony::query()->with('church')->where('public_id', $testimony)->firstOrFail();
        if ($target->church !== null) {
            $context->ensureContains($request, $target->church->scopeReference());
        }
        $this->execute(fn () => $target->delete());

        return ApiResponse::success($request, ['id' => $testimony, 'deleted' => true]);
    }

    public function attendance(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->homeChurchAttendance($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function storeAttendance(Request $request, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'home_church_id' => ['required', 'ulid', 'exists:home_churches,public_id'],
            'service_date' => ['required', 'date'],
            'adults' => ['nullable', 'integer', 'min:0'],
            'children' => ['nullable', 'integer', 'min:0'],
            'first_timers' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $homeChurch = HomeChurch::query()->with('church')->where('public_id', $data['home_church_id'])->firstOrFail();
        $context->ensureContains($request, $homeChurch->church->scopeReference());
        $record = $this->execute(fn (): HomeChurchAttendanceRecord => HomeChurchAttendanceRecord::query()->updateOrCreate(
            [
                'home_church_id' => $homeChurch->getKey(),
                'service_date' => $data['service_date'],
            ],
            [
                'adults' => $data['adults'] ?? 0,
                'children' => $data['children'] ?? 0,
                'first_timers' => $data['first_timers'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ],
        ));
        $record->load(['homeChurch:id,public_id,name,church_id']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($record))->resolve($request), status: 201);
    }

    public function updateAttendance(Request $request, string $attendance, ProtectedAdminContext $context): JsonResponse
    {
        $target = HomeChurchAttendanceRecord::query()->with('homeChurch.church')->where('public_id', $attendance)->firstOrFail();
        $context->ensureContains($request, $target->homeChurch->church->scopeReference());
        $data = $request->validate([
            'service_date' => ['required', 'date'],
            'adults' => ['nullable', 'integer', 'min:0'],
            'children' => ['nullable', 'integer', 'min:0'],
            'first_timers' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $this->execute(function () use ($target, $data): void {
            $target->forceFill([
                'service_date' => $data['service_date'],
                'adults' => $data['adults'] ?? $target->adults,
                'children' => $data['children'] ?? $target->children,
                'first_timers' => $data['first_timers'] ?? $target->first_timers,
                'notes' => $data['notes'] ?? $target->notes,
            ])->save();
        });
        $target->load(['homeChurch:id,public_id,name,church_id']);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    public function destroyAttendance(Request $request, string $attendance, ProtectedAdminContext $context): JsonResponse
    {
        $target = HomeChurchAttendanceRecord::query()->with('homeChurch.church')->where('public_id', $attendance)->firstOrFail();
        $context->ensureContains($request, $target->homeChurch->church->scopeReference());
        $this->execute(fn () => $target->delete());

        return ApiResponse::success($request, ['id' => $attendance, 'deleted' => true]);
    }

    public function churchGroups(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->churchGroups($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function announcements(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        return $this->page($request, $catalog->churchAnnouncements($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function safeguardingIncidents(ListProtectedDomainRecordsRequest $request, ProtectedDomainCatalogQuery $catalog, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);

        return $this->page($request, $catalog->safeguardingIncidents($context->scope($request), $request->validated('filter', []), (int) $request->validated('per_page', 25)));
    }

    public function updatePersonPhone(Request $request, string $person, ProtectedAdminContext $context): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:40'],
            'given_name' => ['nullable', 'string', 'max:100'],
            'family_name' => ['nullable', 'string', 'max:100'],
        ]);
        $target = Person::query()->with('profile')->where('public_id', $person)->firstOrFail();
        $this->execute(function () use ($target, $data): void {
            $profile = $target->profile;
            if ($profile === null) {
                return;
            }
            $profile->forceFill(array_filter([
                'phone' => $data['phone'] ?? $profile->phone,
                'given_name' => $data['given_name'] ?? null,
                'family_name' => $data['family_name'] ?? null,
            ], static fn ($value) => $value !== null))->save();
        });
        $target->load([
            'profile:id,person_id,given_name,middle_name,family_name,preferred_name,phone',
            'user:id,person_id,name,email',
            'memberships' => fn ($m) => $m->with('church:id,public_id,name')->latest('joined_at')->limit(1),
        ]);

        return ApiResponse::success($request, (new ProtectedDomainRecordResource($target))->resolve($request));
    }

    /** @param LengthAwarePaginator<covariant \Illuminate\Database\Eloquent\Model> $paginator */
    private function page(Request $request, LengthAwarePaginator $paginator): JsonResponse
    {
        return ApiResponse::success($request, ProtectedDomainRecordResource::collection($paginator)->resolve($request), meta: [
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ],
        ]);
    }
}
