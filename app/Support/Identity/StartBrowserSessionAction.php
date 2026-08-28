<?php

namespace App\Support\Identity;

use App\Models\User;
use App\Support\Security\RecordSecuritySessionAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class StartBrowserSessionAction
{
    public function __construct(
        private RecordSecuritySessionAction $recordSecuritySession,
    ) {}

    public function handle(Request $request, User $user, bool $authenticate = false): void
    {
        if ($authenticate) {
            Auth::guard('web')->login($user);
        }

        $request->session()->regenerate();

        try {
            $securitySession = $this->recordSecuritySession->handle(
                $user,
                expiresAt: now()->addMinutes((int) config('session.lifetime')),
                actor: $user,
            );
            $request->session()->put('security_session_id', $securitySession->public_id);
        } catch (Throwable $exception) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw $exception;
        }
    }
}
