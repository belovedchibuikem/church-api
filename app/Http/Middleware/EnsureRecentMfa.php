<?php

namespace App\Http\Middleware;

use App\Models\SecuritySession;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecentMfa
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && ! $user->mfaMethods()->usable()->exists()) {
            return $next($request);
        }

        $verifiedAt = $this->verifiedAt($request);
        $maximumAge = min((int) config('api.mfa.recent_seconds'), 43_200);

        if ($verifiedAt === null || $verifiedAt->isBefore(now()->subSeconds($maximumAge))) {
            throw new AuthorizationException('Recent multi-factor authentication is required.');
        }

        return $next($request);
    }

    private function verifiedAt(Request $request): ?Carbon
    {
        $securitySession = $request->attributes->get('security_session');

        if ($securitySession instanceof SecuritySession) {
            return $securitySession->mfa_verified_at;
        }

        if (! $request->hasSession()) {
            return null;
        }

        $sessionVerifiedAt = $request->session()->get('auth.mfa_verified_at')
            ?? $request->session()->get('mfa_verified_at');

        if ($sessionVerifiedAt instanceof Carbon) {
            return $sessionVerifiedAt;
        }

        if (is_string($sessionVerifiedAt) || is_int($sessionVerifiedAt)) {
            try {
                return Carbon::parse($sessionVerifiedAt);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
