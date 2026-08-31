<?php

namespace App\Support\Identity;

use App\Models\User;
use App\Support\Security\ClientNetworkContext;
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
            // Null expiry = persist until explicit logout; Laravel cookie idle uses SESSION_LIFETIME.
            $network = ClientNetworkContext::fromRequest($request);
            $securitySession = $this->recordSecuritySession->handle(
                $user,
                expiresAt: null,
                actor: $user,
                ipAddress: $network['ip'],
                countryCode: $network['country'],
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
