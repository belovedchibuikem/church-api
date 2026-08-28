<?php

namespace App\Models;

use App\Church\ChurchMembershipStatus;
use Database\Factories\ChurchMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'person_id',
    'church_id',
    'home_church_id',
    'joined_at',
])]
class ChurchMembership extends Model
{
    /** @use HasFactory<ChurchMembershipFactory> */
    use HasFactory, HasUlids;

    protected $attributes = ['status' => 'active', 'active_marker' => 1];

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
            'status' => ChurchMembershipStatus::class,
            'active_marker' => 'integer',
            'joined_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
