<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListAuditRecordsRequest;
use App\Http\Resources\Api\V1\Admin\AuditRecordResource;
use App\Models\AccessDecision;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class AuditReviewController extends Controller
{
    public function auditEvents(ListAuditRecordsRequest $request, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $filters = $request->validated('filter', []);
        $query = AuditEvent::query()->with('actor:id,public_id');
        $this->applyActor($query, $filters);

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        $this->applyRange($query, $filters, 'occurred_at');

        return $this->page($request, $query->latest('occurred_at')->paginate((int) $request->validated('per_page', 25)));
    }

    public function accessDecisions(ListAuditRecordsRequest $request, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $filters = $request->validated('filter', []);
        $query = AccessDecision::query()->with('actor:id,public_id');
        $this->applyActor($query, $filters);

        if (isset($filters['permission_code'])) {
            $query->where('permission_code', $filters['permission_code']);
        }

        if (array_key_exists('allowed', $filters)) {
            $query->where('allowed', (bool) $filters['allowed']);
        }

        $this->applyRange($query, $filters, 'decided_at');

        return $this->page($request, $query->latest('decided_at')->paginate((int) $request->validated('per_page', 25)));
    }

    private function applyActor(Builder $query, array $filters): void
    {
        if (isset($filters['actor_id'])) {
            $actorId = User::query()->where('public_id', $filters['actor_id'])->value('id');
            $query->where('actor_user_id', $actorId ?? 0);
        }
    }

    private function applyRange(Builder $query, array $filters, string $column): void
    {
        if (isset($filters['from'])) {
            $query->where($column, '>=', CarbonImmutable::parse((string) $filters['from'])->utc());
        }

        if (isset($filters['to'])) {
            $query->where($column, '<=', CarbonImmutable::parse((string) $filters['to'])->utc());
        }
    }

    private function page(ListAuditRecordsRequest $request, LengthAwarePaginator $paginator): JsonResponse
    {
        return ApiResponse::success($request, AuditRecordResource::collection($paginator->getCollection())->resolve($request), [
            'pagination' => ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ]);
    }
}
