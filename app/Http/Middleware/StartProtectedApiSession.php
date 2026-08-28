<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;

class StartProtectedApiSession
{
    public function __construct(private StartSession $startSession) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->hasBearerCredential($request)) {
            return $next($request);
        }

        return $this->startSession->handle($request, $next);
    }

    private function hasBearerCredential(Request $request): bool
    {
        $authorization = $request->header('Authorization');

        return is_string($authorization)
            && preg_match('/\ABearer\s+\S+\z/i', $authorization) === 1;
    }
}
