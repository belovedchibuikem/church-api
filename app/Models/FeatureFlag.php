<?php

namespace App\Models;

use Database\Factories\FeatureFlagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'key',
    'environment',
    'scope_type',
    'scope_key',
    'context_hash',
    'rollout_percentage',
    'starts_at',
    'ends_at',
])]
class FeatureFlag extends Model
{
    /** @use HasFactory<FeatureFlagFactory> */
    use HasFactory, HasUlids;

    protected $attributes = [
        'is_enabled' => false,
        'rollout_percentage' => 100,
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'rollout_percentage' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }
}
