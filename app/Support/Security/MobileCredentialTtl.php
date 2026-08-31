<?php

namespace App\Support\Security;

use Illuminate\Support\Carbon;

/**
 * Mobile access and refresh credentials share a minimum 30-day lifetime.
 * The security session expires at that same bound so the device is signed out
 * automatically — refresh does not extend the original window.
 */
final class MobileCredentialTtl
{
    public const MIN_SESSION_SECONDS = 2_592_000;

    public const MAX_SESSION_SECONDS = 7_776_000;

    public static function sessionSeconds(): int
    {
        $configured = (int) config('api.mobile.refresh_ttl_seconds', self::MIN_SESSION_SECONDS);

        return min(max($configured, self::MIN_SESSION_SECONDS), self::MAX_SESSION_SECONDS);
    }

    public static function accessSeconds(): int
    {
        $configured = (int) config('api.mobile.access_ttl_seconds', self::MIN_SESSION_SECONDS);
        $session = self::sessionSeconds();

        return min(max($configured, self::MIN_SESSION_SECONDS), $session);
    }

    public static function sessionExpiresAt(): Carbon
    {
        return now()->utc()->addSeconds(self::sessionSeconds());
    }
}
