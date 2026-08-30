<?php

namespace App\Models;

use App\Livestream\LivestreamStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'church_id',
    'title',
    'subtitle',
    'host_name',
    'provider',
    'external_id',
    'watch_url',
    'embed_url',
    'status',
    'viewer_count',
    'reaction_count',
    'starts_at',
    'ended_at',
])]
class Livestream extends Model
{
    use HasUlids;

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

    public function comments(): HasMany
    {
        return $this->hasMany(LivestreamComment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LivestreamStatus::class,
            'viewer_count' => 'integer',
            'reaction_count' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
