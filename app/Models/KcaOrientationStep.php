<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'slug',
    'title',
    'subtitle',
    'body',
    'display_type',
    'sequence',
    'is_active',
])]
class KcaOrientationStep extends Model
{
    use HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param  Builder<self>  $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sequence')->orderBy('id');
    }
}
