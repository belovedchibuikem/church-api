<?php

namespace App\Models;

use App\Media\HasMedia;
use App\Support\Authorization\ScopeReference;
use Database\Factories\ChurchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['location_id', 'administrative_unit_id', 'name', 'published_at'])]
class Church extends Model
{
    /** @use HasFactory<ChurchFactory> */
    use HasFactory, HasMedia, HasUlids;

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function scopeReference(): ScopeReference
    {
        return new ScopeReference('church', $this->public_id);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class);
    }

    public function homeChurches(): HasMany
    {
        return $this->hasMany(HomeChurch::class);
    }

    public function homeChurchApplications(): HasMany
    {
        return $this->hasMany(HomeChurchApplication::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ChurchMembership::class);
    }

    public function firstTimers(): HasMany
    {
        return $this->hasMany(FirstTimer::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime'];
    }
}
