<?php

namespace App\Models;

use App\Church\HomeChurchStatus;
use App\Media\HasMedia;
use App\Support\Authorization\ScopeReference;
use Database\Factories\HomeChurchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'church_id',
    'leader_person_id',
    'location_id',
    'administrative_unit_id',
    'name',
    'meeting_schedules',
])]
class HomeChurch extends Model
{
    /** @use HasFactory<HomeChurchFactory> */
    use HasFactory, HasMedia, HasUlids;

    protected $attributes = ['status' => 'active'];

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
        return new ScopeReference('home_church', $this->public_id);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'leader_person_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class);
    }

    public function application(): HasOne
    {
        return $this->hasOne(HomeChurchApplication::class);
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
        return [
            'status' => HomeChurchStatus::class,
            'meeting_schedules' => 'array',
        ];
    }
}
