<?php

namespace App\Services\Admin;

use App\Http\Middleware\RequirePermissionAndScope;
use App\Models\User;
use App\Support\Authorization\Contracts\ScopeContainmentResolver;
use App\Support\Authorization\ScopeReference;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProtectedAdminContext
{
    public function __construct(private ScopeContainmentResolver $scopeContainmentResolver) {}

    public function actor(Request $request): User
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }

        return $actor;
    }

    public function scope(Request $request): ScopeReference
    {
        $scope = $request->attributes->get(RequirePermissionAndScope::SCOPE_ATTRIBUTE);

        if (! $scope instanceof ScopeReference) {
            throw new LogicException('The protected route did not supply an authorization scope.');
        }

        return $scope;
    }

    public function ensureGlobal(Request $request): void
    {
        if (! $this->isGlobal($this->scope($request))) {
            throw new AccessDeniedHttpException;
        }
    }

    public function ensureContains(Request $request, ScopeReference $targetScope): void
    {
        if (! $this->scopeContainmentResolver->contains(
            $this->scope($request),
            $targetScope,
            $this->actor($request),
        )) {
            throw new NotFoundHttpException;
        }
    }

    public function isGlobal(ScopeReference $scope): bool
    {
        return $scope->type === 'global' && $scope->key === 'platform';
    }
}
