<?php

namespace App\Support\Press;

use App\Models\PressPublication;
use App\Models\PressPublicationTransition;
use App\Models\User;
use App\Press\PressPublicationStatus;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class RecordPressPublicationTransitionAction
{
    public function handle(
        PressPublication $publication,
        User $actor,
        PressPublicationStatus $from,
        PressPublicationStatus $to,
        string $reasonCode,
    ): PressPublicationTransition {
        $correlationId = Context::get('correlation_id');
        $transition = new PressPublicationTransition;
        $transition->forceFill([
            'press_publication_id' => $publication->getKey(),
            'actor_user_id' => $actor->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'reason_code' => $reasonCode,
            'correlation_id' => is_string($correlationId) && Str::isUuid($correlationId) ? $correlationId : null,
            'occurred_at' => now()->utc(),
        ]);
        $transition->save();

        return $transition;
    }
}
