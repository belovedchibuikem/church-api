<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
class PrayerRequest extends Model
{
    use HasUlids;

    protected $attributes = [
        'status' => 'open',
    ];

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

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assigned_to_person_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PrayerRequestAssignment::class);
    }

    protected function casts(): array
    {
        return ['assigned_at' => 'immutable_datetime'];
    }
}
