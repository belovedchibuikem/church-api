<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'church_id',
    'title',
    'activity_type',
    'souls_reached',
    'decisions',
    'occurred_at',
    'status',
    'notes',
])]
class EvangelismActivity extends Model
{
    use HasUlids;

    protected $attributes = [
        'activity_type' => 'outreach',
        'souls_reached' => 0,
        'decisions' => 0,
        'status' => 'completed',
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

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'souls_reached' => 'integer',
            'decisions' => 'integer',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
