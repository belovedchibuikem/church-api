<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'home_church_id',
    'service_date',
    'adults',
    'children',
    'first_timers',
    'notes',
])]
class HomeChurchAttendanceRecord extends Model
{
    use HasUlids;

    protected $attributes = [
        'adults' => 0,
        'children' => 0,
        'first_timers' => 0,
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

    public function homeChurch(): BelongsTo
    {
        return $this->belongsTo(HomeChurch::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'adults' => 'integer',
            'children' => 'integer',
            'first_timers' => 'integer',
        ];
    }
}
