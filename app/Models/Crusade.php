<?php

namespace App\Models;

use App\Media\HasMedia;
use Database\Factories\CrusadeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'location_id', 'starts_at', 'ends_at', 'published_at'])]
class Crusade extends Model
{
    /** @use HasFactory<CrusadeFactory> */
    use HasFactory, HasMedia, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(MissionInvitation::class);
    }

    public function teamAssignments(): HasMany
    {
        return $this->hasMany(MissionTeamAssignment::class);
    }

    public function soulJourneys(): HasMany
    {
        return $this->hasMany(MissionSoulJourney::class);
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
