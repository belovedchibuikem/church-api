<?php

namespace App\Models;

use App\Exceptions\PressTransitionImmutableException;
use App\Press\PressPublicationStatus;
use Database\Factories\PressPublicationTransitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class PressPublicationTransition extends Model
{
    /** @use HasFactory<PressPublicationTransitionFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(PressPublication::class, 'press_publication_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => PressPublicationStatus::class,
            'to_status' => PressPublicationStatus::class,
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
