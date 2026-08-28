<?php

namespace App\Http\Middleware;

use App\Models\SecuritySession;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveBrowserSecuritySession
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (is_string($request->bearerToken()) && $request->bearerToken() !== '') {
            return $next($request);
        }

        $user = $request->user('web');
        $securitySessionId = $request->session()->get('security_session_id');

        $securitySession = is_string($securitySessionId)
            ? SecuritySession::query()
                ->usable()
                ->where('public_id', $securitySessionId)
                ->first()
            : null;

        if (
            ! $user instanceof User
            || $securitySession === null
            || $securitySession->user_id !== $user->getKey()
        ) {
            throw new AuthenticationException;
        }

        if ($securitySession->last_seen_at->lt(now()->subMinute())) {
            $securitySession->forceFill(['last_seen_at' => now()->utc()])->save();
        }

        return $next($request);
    }
}
