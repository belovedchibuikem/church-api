<?php

namespace App\Http\Middleware;

use App\Models\MobileAccessToken;
use App\Models\User;
use App\Support\Security\MobileCredentialHasher;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileAccessToken
{
    public function __construct(private MobileCredentialHasher $hasher) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $existingUser = $request->user();

        if ($existingUser instanceof User && ! $existingUser->isSuspended()) {
            return $next($request);
        }

        $plainAccessToken = $request->bearerToken();
        $deviceIdentifier = $request->header('X-Device-Identifier');

        if (! is_string($plainAccessToken) || ! is_string($deviceIdentifier) || $deviceIdentifier === '') {
            throw new AuthenticationException;
        }

        $accessToken = MobileAccessToken::query()
            ->with(['user', 'device', 'securitySession'])
            ->where('token_hash', $this->hasher->hash($plainAccessToken))
            ->first();

        if ($accessToken === null || ! $this->isUsable($accessToken, $deviceIdentifier)) {
            throw new AuthenticationException;
        }

        $user = $accessToken->user;
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        Auth::guard('web')->setUser($user);
        $request->setUserResolver(fn (): User => $user);
        $request->attributes->set('security_session', $accessToken->securitySession);
        $request->attributes->set('authenticated_device', $accessToken->device);
        $request->attributes->set('mobile_access_credential', $accessToken);

        $this->recordUse($accessToken);

        return $next($request);
    }

    private function isUsable(MobileAccessToken $accessToken, string $deviceIdentifier): bool
    {
        $user = $accessToken->user;
        $device = $accessToken->device;
        $securitySession = $accessToken->securitySession;

        if ($user === null || $device === null || $securitySession === null) {
            return false;
        }

        return $accessToken->revoked_at === null
            && $accessToken->expires_at->isFuture()
            && ! $user->isSuspended()
            && $device->revoked_at === null
            && hash_equals($device->identifier_hash, $this->hasher->hash($deviceIdentifier))
            && $securitySession->revoked_at === null
            && ($securitySession->expires_at === null || $securitySession->expires_at->isFuture())
            && $securitySession->user_id === $accessToken->user_id
            && $securitySession->device_id === $accessToken->device_id;
    }

    private function recordUse(MobileAccessToken $accessToken): void
    {
        if ($accessToken->last_used_at !== null && $accessToken->last_used_at->isAfter(now()->subMinutes(5))) {
            return;
        }

        $usedAt = now()->utc();
        $accessToken->forceFill(['last_used_at' => $usedAt])->save();
        $accessToken->securitySession->forceFill(['last_seen_at' => $usedAt])->save();
        $accessToken->device->forceFill(['last_seen_at' => $usedAt])->save();
    }
}
