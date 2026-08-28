<?php

namespace App\Support\Press;

use App\Models\PressTranslation;
use App\Models\PressTranslationTransition;
use App\Models\User;
use App\Press\PressTranslationStatus;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class RecordPressTranslationTransitionAction
{
    public function handle(
        PressTranslation $translation,
        User $actor,
        PressTranslationStatus $from,
        PressTranslationStatus $to,
        string $reasonCode,
    ): PressTranslationTransition {
        $correlationId = Context::get('correlation_id');
        $transition = new PressTranslationTransition;
        $transition->forceFill([
            'press_translation_id' => $translation->getKey(),
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
