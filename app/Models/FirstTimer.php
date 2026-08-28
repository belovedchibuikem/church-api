<?php

namespace App\Models;

use Database\Factories\FirstTimerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['person_id', 'church_id', 'home_church_id', 'registered_at'])]
class FirstTimer extends Model
{
    /** @use HasFactory<FirstTimerFactory> */
    use HasFactory, HasUlids;

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function homeChurch(): BelongsTo
    {
        return $this->belongsTo(HomeChurch::class);
    }

    public function followUpTasks(): HasMany
    {
        return $this->hasMany(FollowUpTask::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'registered_at' => 'immutable_datetime',
            'contacted_at' => 'immutable_datetime',
        ];
    }
}
