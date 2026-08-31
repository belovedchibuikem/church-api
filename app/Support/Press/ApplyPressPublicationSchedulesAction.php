<?php

namespace App\Support\Press;

use App\Models\PressPublication;
use App\Press\PressPublicationStatus;
use Illuminate\Support\Carbon;

class ApplyPressPublicationSchedulesAction
{
    public function __construct(private TransitionPressPublicationAction $transition) {}

    public function handle(?Carbon $now = null): int
    {
        $now = $now ?? now()->utc();
        $applied = 0;

        PressPublication::query()
            ->where('status', PressPublicationStatus::Scheduled->value)
            ->whereNotNull('scheduled_publish_at')
            ->where('scheduled_publish_at', '<=', $now)
            ->orderBy('id')
            ->each(function (PressPublication $publication) use (&$applied): void {
                $actor = $publication->scheduledBy;
                if ($actor === null) {
                    return;
                }

                $this->transition->handle($publication, PressPublicationStatus::Published, $actor, 'schedule.published');
                $applied++;
            });

        PressPublication::query()
            ->whereIn('status', [
                PressPublicationStatus::Published->value,
                PressPublicationStatus::Distribution->value,
            ])
            ->whereNotNull('scheduled_unpublish_at')
            ->where('scheduled_unpublish_at', '<=', $now)
            ->orderBy('id')
            ->each(function (PressPublication $publication) use (&$applied): void {
                $actor = $publication->scheduledBy;
                if ($actor === null) {
                    return;
                }

                $this->transition->handle($publication, PressPublicationStatus::Unpublished, $actor, 'schedule.unpublished');
                $applied++;
            });

        return $applied;
    }
}
