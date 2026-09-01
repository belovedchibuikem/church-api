<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
class BiblePlanEnrollment extends Model
{
    use HasUlids;

    protected $attributes = [
        'status' => 'active',
        'timezone' => 'UTC',
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

    public function completions(): HasMany
    {
        return $this->hasMany(BiblePlanDayCompletion::class, 'enrollment_id');
    }

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
        ];
    }
}
