<?php

namespace App\Models;

use App\Church\ChurchGroupMembershipStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'church_id',
    'name',
    'description',
    'leader_person_id',
    'capacity',
    'is_published',
])]
class ChurchGroup extends Model
{
    use HasUlids;

    protected $attributes = [
        'is_published' => true,
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

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'leader_person_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ChurchGroupMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->hasMany(ChurchGroupMembership::class)
            ->where('status', ChurchGroupMembershipStatus::Active);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_published' => 'boolean',
        ];
    }
}
