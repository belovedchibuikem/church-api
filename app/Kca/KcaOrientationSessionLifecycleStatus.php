<?php

namespace App\Kca;

use App\Models\KcaOrientationSession;
use Carbon\CarbonImmutable;

enum KcaOrientationSessionLifecycleStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Ended = 'ended';

    public static function forSession(KcaOrientationSession $session, ?CarbonImmutable $now = null): self
    {
        $now ??= CarbonImmutable::now('UTC');

        if ($session->published_at === null) {
            return self::Draft;
        }

        if ($session->published_at->gt($now)) {
            return self::Scheduled;
        }

        $endsAt = $session->ends_at ?? $session->starts_at;
        if ($endsAt->lt($now)) {
            return self::Ended;
        }

        if ($session->starts_at->gt($now)) {
            return self::Scheduled;
        }

        return self::Published;
    }
}
