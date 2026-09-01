<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'kca_assignment_id',
    'parent_id',
    'depth',
    'given_name',
    'family_name',
    'phone',
    'email',
    'notes',
    'won_at',
])]
class KcaSoulWin extends Model
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

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(KcaAssignment::class, 'kca_assignment_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'won_at' => 'immutable_datetime',
        ];
    }
}
