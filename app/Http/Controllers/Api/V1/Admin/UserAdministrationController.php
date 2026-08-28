<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\UserAccountStateConflictException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\RequirePermissionAndScope;
use App\Http\Requests\Api\V1\Admin\ListUsersRequest;
use App\Http\Requests\Api\V1\Admin\ReactivateUserRequest;
use App\Http\Requests\Api\V1\Admin\ShowUserRequest;
use App\Http\Requests\Api\V1\Admin\SuspendUserRequest;
use App\Http\Resources\Api\V1\Admin\UserResource;
use App\Models\User;
use App\Queries\Admin\ListScopedUsersQuery;
use App\Services\Admin\AdministerUserAccountService;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\ScopeReference;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UserAdministrationController extends Controller
{
    public function index(ListUsersRequest $request, ListScopedUsersQuery $users): JsonResponse
    {
        $paginator = $users->paginate(
            $this->actor($request),
            $this->scope($request),
            $request->filters(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::success(
            $request,
            UserResource::collection($paginator->getCollection())->resolve($request),
            ['pagination' => $this->pagination($paginator)],
        );
    }

    public function show(ShowUserRequest $request, string $user, ListScopedUsersQuery $users): JsonResponse
    {
        $target = $users->findOrFail($this->actor($request), $this->scope($request), $user);

        return ApiResponse::success($request, (new UserResource($target))->resolve($request));
    }

    public function suspend(
        SuspendUserRequest $request,
        string $user,
        AdministerUserAccountService $accounts,
    ): JsonResponse {
        try {
            $target = $accounts->suspend(
                $this->actor($request),
                $this->scope($request),
                $user,
                (string) $request->validated('reason'),
            );
        } catch (UserAccountStateConflictException $exception) {
            throw new ConflictHttpException(previous: $exception);
        }

        return ApiResponse::success($request, (new UserResource($target))->resolve($request));
    }

    public function reactivate(
        ReactivateUserRequest $request,
        string $user,
        AdministerUserAccountService $accounts,
    ): JsonResponse {
        $target = $accounts->reactivate($this->actor($request), $this->scope($request), $user);

        return ApiResponse::success($request, (new UserResource($target))->resolve($request));
    }

    private function actor(ListUsersRequest|ShowUserRequest|SuspendUserRequest|ReactivateUserRequest $request): User
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new LogicException('The protected route did not supply an authenticated user.');
        }

        return $actor;
    }

    private function scope(ListUsersRequest|ShowUserRequest|SuspendUserRequest|ReactivateUserRequest $request): ScopeReference
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
