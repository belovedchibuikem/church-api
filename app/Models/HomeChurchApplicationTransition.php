<?php

namespace App\Models;

use App\Church\HomeChurchApplicationStatus;
use Database\Factories\HomeChurchApplicationTransitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'home_church_application_id',
    'actor_user_id',
    'from_status',
    'to_status',
    'reason_code',
    'correlation_id',
    'occurred_at',
])]
class HomeChurchApplicationTransition extends Model
{
    /** @use HasFactory<HomeChurchApplicationTransitionFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(HomeChurchApplication::class, 'home_church_application_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => HomeChurchApplicationStatus::class,
            'to_status' => HomeChurchApplicationStatus::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Home Church application transitions are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Home Church application transitions are immutable.');
        });
    }
}
