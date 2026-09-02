<?php

namespace App\Events;

use App\Models\MinistryEvent;
use Carbon\CarbonImmutable;

enum MinistryEventLifecycleStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Ended = 'ended';

    public static function forEvent(MinistryEvent $event, ?CarbonImmutable $now = null): self
    {
        $now ??= CarbonImmutable::now('UTC');

        if ($event->published_at === null) {
            return self::Draft;
        }

        if ($event->published_at->gt($now)) {
            return self::Scheduled;
        }

        if ($event->ends_at->lt($now)) {
            return self::Ended;
        }

        return self::Published;
    }
}
