<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequirePermissionAndScope;
use App\Http\Requests\Api\V1\Admin\ListAdminWorkItemsRequest;
use App\Http\Requests\Api\V1\Admin\StoreAdminWorkItemRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminWorkItemRequest;
use App\Http\Resources\Api\V1\Admin\AdminWorkItemResource;
use App\Models\AdminWorkItem;
use App\Models\User;
use App\Queries\Admin\ListScopedUsersQuery;
use App\Support\Administration\ManageAdminWorkItemAction;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\ScopeReference;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AdminWorkItemController extends Controller
{
    public function index(ListAdminWorkItemsRequest $request): JsonResponse
    {
        $filters = $request->validated('filter', []);
        $sort = (string) $request->validated('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $query = AdminWorkItem::query()
            ->with(['assignee:id,public_id,name,email', 'creator:id,public_id,name'])
            ->when(isset($filters['search']), function (Builder $builder) use ($filters): void {
                $search = addcslashes(trim((string) $filters['search']), '\\%_');
                $builder->where(fn (Builder $inner): Builder => $inner
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%"));
            })
            ->when(isset($filters['status']), fn (Builder $builder): Builder => $builder->where('status', $filters['status']))
            ->when(isset($filters['priority']), fn (Builder $builder): Builder => $builder->where('priority', $filters['priority']))
            ->when(isset($filters['assigned_to']), function (Builder $builder) use ($filters): void {
                $userId = User::query()->where('public_id', $filters['assigned_to'])->value('id');
                $builder->where('assigned_to_user_id', $userId ?? 0);
            })
            ->orderBy($column, $direction)
            ->orderBy('id');

        $paginator = $query->paginate((int) $request->validated('per_page', 25))->withQueryString();

        return ApiResponse::success(
            $request,
            AdminWorkItemResource::collection($paginator->getCollection())->resolve($request),
            ['pagination' => $this->pagination($paginator)],
        );
    }

    public function store(
        StoreAdminWorkItemRequest $request,
        ManageAdminWorkItemAction $action,
        ListScopedUsersQuery $users,
    ): JsonResponse {
        $item = $this->mutate($action, $request, fn (ManageAdminWorkItemAction $manage, User $actor, ScopeReference $scope): AdminWorkItem => $manage->create(
            $actor,
            $scope,
            $this->payload($request->validated(), $actor, $scope, $users),
        ));

        return ApiResponse::success($request, (new AdminWorkItemResource($item->load(['assignee', 'creator'])))->resolve($request), status: 201);
    }

    public function show(string $workItem): JsonResponse
    {
        $item = AdminWorkItem::query()
            ->with(['assignee:id,public_id,name,email', 'creator:id,public_id,name'])
            ->where('public_id', $workItem)
            ->firstOrFail();

        return ApiResponse::success(request(), (new AdminWorkItemResource($item))->resolve(request()));
    }

    public function update(
        UpdateAdminWorkItemRequest $request,
        string $workItem,
        ManageAdminWorkItemAction $action,
        ListScopedUsersQuery $users,
    ): JsonResponse {
        $item = AdminWorkItem::query()->where('public_id', $workItem)->firstOrFail();
        $updated = $this->mutate($action, $request, fn (ManageAdminWorkItemAction $manage, User $actor, ScopeReference $scope): AdminWorkItem => $manage->update(
            $actor,
            $scope,
            $item,
            $this->payload($request->validated(), $actor, $scope, $users),
        ));

        return ApiResponse::success($request, (new AdminWorkItemResource($updated->load(['assignee', 'creator'])))->resolve($request));
    }

    public function archive(
        Request $request,
        string $workItem,
        ManageAdminWorkItemAction $action,
    ): JsonResponse {
        $item = AdminWorkItem::query()->where('public_id', $workItem)->firstOrFail();
        $updated = $this->mutate($action, $request, fn (ManageAdminWorkItemAction $manage, User $actor, ScopeReference $scope): AdminWorkItem => $manage->archive($actor, $scope, $item));

        return ApiResponse::success($request, (new AdminWorkItemResource($updated->load(['assignee', 'creator'])))->resolve($request));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated, User $actor, ScopeReference $scope, ListScopedUsersQuery $users): array
    {
        $assigneePublicId = $validated['assigned_to_user_id'] ?? null;
        if (is_string($assigneePublicId) && $assigneePublicId !== '') {
            $assignee = $users->findOrFail($actor, $scope, $assigneePublicId);
            $validated['assigned_to_user_id'] = $assignee->getKey();
        } elseif (array_key_exists('assigned_to_user_id', $validated) && $assigneePublicId === null) {
            $validated['assigned_to_user_id'] = null;
        }

        return $validated;
    }

    private function mutate(ManageAdminWorkItemAction $action, $request, callable $operation): AdminWorkItem
    {
        try {
            return $operation($action, $this->actor($request), $this->scope($request));
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException(previous: $exception);
        }
    }

    private function actor($request): User
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new LogicException('The protected route did not supply an authenticated user.');
        }

        return $actor;
    }

    private function scope($request): ScopeReference
    {
        $scope = $request->attributes->get(RequirePermissionAndScope::SCOPE_ATTRIBUTE);
        if (! $scope instanceof ScopeReference) {
            throw new LogicException('The protected route did not supply an authorization scope.');
        }

        return $scope;
    }

    /** @return array{current_page: int, per_page: int, last_page: int, total: int} */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }
}
