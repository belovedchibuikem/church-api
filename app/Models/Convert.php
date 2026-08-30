<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'person_id',
    'church_id',
    'home_church_id',
    'converted_at',
    'baptized_at',
    'source',
    'status',
    'notes',
])]
class Convert extends Model
{
    use HasUlids;

    protected $attributes = [
        'status' => 'active',
    ];

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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'converted_at' => 'immutable_datetime',
            'baptized_at' => 'immutable_datetime',
        ];
    }
}
