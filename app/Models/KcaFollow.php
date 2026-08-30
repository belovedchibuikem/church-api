<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'follower_person_id',
    'followed_person_id',
])]
class KcaFollow extends Model
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

    public function follower(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'follower_person_id');
    }

    public function followed(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'followed_person_id');
    }
}
