<?php

namespace App\Models;

use App\Exceptions\PressTransitionImmutableException;
use App\Press\PressTranslationStatus;
use Database\Factories\PressTranslationTransitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class PressTranslationTransition extends Model
{
    /** @use HasFactory<PressTranslationTransitionFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(PressTranslation::class, 'press_translation_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => PressTranslationStatus::class,
            'to_status' => PressTranslationStatus::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new PressTransitionImmutableException;
        });

        static::deleting(function (): never {
            throw new PressTransitionImmutableException;
        });
    }
}
