<?php

namespace App\Casts;

use App\Kca\KcaAssignmentState;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<KcaAssignmentState, KcaAssignmentState|string|null>
 */
final class KcaAssignmentStateCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): KcaAssignmentState
    {
        return KcaAssignmentState::fromStored(is_string($value) ? $value : null);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof KcaAssignmentState) {
            return $value->value;
        }

        return KcaAssignmentState::fromStored(is_string($value) ? $value : null)->value;
    }
}
