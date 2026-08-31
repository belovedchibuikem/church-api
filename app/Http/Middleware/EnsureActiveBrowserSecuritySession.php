<?php

namespace App\Http\Middleware;

use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Security\ClientNetworkContext;
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

        $now = now()->utc();
        $lifetimeMinutes = max(1, (int) config('session.lifetime'));
        $network = ClientNetworkContext::fromRequest($request);
        $touch = ['last_seen_at' => $now];
        if ($network['ip'] !== null) {
            $touch['last_ip'] = $network['ip'];
        }
        if ($network['country'] !== null) {
            $touch['last_country'] = $network['country'];
        }

        // Slide absolute expiry on activity so active admins stay signed in until logout.
        if ($securitySession->expires_at !== null) {
            $touch['expires_at'] = $now->copy()->addMinutes($lifetimeMinutes);
        }

        if ($securitySession->last_seen_at->lt($now->copy()->subMinute())) {
            $securitySession->forceFill($touch)->save();
        }

        return $next($request);
    }
}
