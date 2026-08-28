<?php

namespace App\Support\Identity;

use App\Models\SecuritySession;
use App\Models\User;
use App\Support\Security\RevokeSecuritySessionAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EndBrowserSessionAction
{
    public function __construct(
        private RevokeSecuritySessionAction $revokeSecuritySession,
    ) {}

    public function handle(Request $request, User $user): void
    {
        try {
            $securitySessionId = $request->session()->get('security_session_id');

            if (is_string($securitySessionId)) {
                $securitySession = SecuritySession::query()
                    ->whereBelongsTo($user)
                    ->where('public_id', $securitySessionId)
                    ->first();

                if ($securitySession !== null) {
                    $this->revokeSecuritySession->handle(
                        $securitySession,
                        'browser.logout',
                        $user,
                    );
                }
            }
        } finally {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
