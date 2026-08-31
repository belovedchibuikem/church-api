<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListAuditRecordsRequest;
use App\Http\Resources\Api\V1\Admin\AuditRecordResource;
use App\Models\AccessDecision;
use App\Models\AuditEvent;
use App\Models\SecuritySession;
use App\Models\User;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditReviewController extends Controller
{
    public function sessions(ListAuditRecordsRequest $request, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $filters = $request->validated('filter', []);
        $query = SecuritySession::query()
            ->with(['user:id,public_id,name,email', 'device:id,public_id,label,platform,device_type']);

        if (isset($filters['actor_id'])) {
            $actorId = User::query()->where('public_id', $filters['actor_id'])->value('id');
            $query->where('user_id', $actorId ?? 0);
        }

        $this->applyRange($query, $filters, 'started_at');

        return $this->page(
            $request,
            $query->latest('started_at')->paginate((int) $request->validated('per_page', 25)),
        );
    }

    public function auditEvents(ListAuditRecordsRequest $request, ProtectedAdminContext $context): JsonResponse|StreamedResponse
    {
        $context->ensureGlobal($request);
        $filters = $request->validated('filter', []);
        $query = AuditEvent::query()->with('actor:id,public_id');
        $this->applyActor($query, $filters);

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['target_type'])) {
            $query->where('target_type', $filters['target_type']);
        }

        if (isset($filters['target_types'])) {
            $types = array_values(array_filter(array_map(
                static fn (string $type): string => trim($type),
                explode(',', (string) $filters['target_types']),
            )));
            if ($types !== []) {
                $query->whereIn('target_type', $types);
            }
        }

        $this->applyRange($query, $filters, 'occurred_at');

        if ($request->validated('format') === 'csv') {
            $rows = $query->latest('occurred_at')->limit(5000)->get();

            return response()->streamDownload(function () use ($rows): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }
                fputcsv($handle, ['id', 'actor_user_id', 'action', 'target_type', 'target_id', 'scope_type', 'scope_id', 'occurred_at']);
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->public_id,
                        $row->actor?->public_id,
                        $row->action,
                        $row->target_type,
                        $row->target_id,
                        $row->scope_type,
                        $row->scope_id,
                        $row->occurred_at?->utc()->toIso8601String(),
                    ]);
                }
                fclose($handle);
            }, 'audit-events.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return $this->page($request, $query->latest('occurred_at')->paginate((int) $request->validated('per_page', 25)));
    }

    public function showAuditEvent(Request $request, string $auditEvent, ProtectedAdminContext $context): JsonResponse|StreamedResponse
    {
        $context->ensureGlobal($request);
        $event = AuditEvent::query()->with('actor:id,public_id')->where('public_id', $auditEvent)->firstOrFail();

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($event): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }
                fputcsv($handle, ['id', 'actor_user_id', 'action', 'target_type', 'target_id', 'scope_type', 'scope_id', 'correlation_id', 'occurred_at']);
                fputcsv($handle, [
                    $event->public_id,
                    $event->actor?->public_id,
                    $event->action,
                    $event->target_type,
                    $event->target_id,
                    $event->scope_type,
                    $event->scope_id,
                    $event->correlation_id,
                    $event->occurred_at?->utc()->toIso8601String(),
                ]);
                fclose($handle);
            }, 'audit-event-'.$event->public_id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return ApiResponse::success($request, (new AuditRecordResource($event))->resolve($request));
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
