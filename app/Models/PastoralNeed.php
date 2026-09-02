<?php

namespace App\Models;

use App\Support\Identity\PersonDisplayName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'person_id',
    'church_id',
    'home_church_id',
    'category',
    'summary',
    'status',
])]
class PastoralNeed extends Model
{
    use HasUlids;

    protected $attributes = [
        'status' => 'open',
    ];

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

    public function scopeType(): string
    {
        if ($this->home_church_id !== null) {
            return 'home_church';
        }

        if ($this->church_id !== null) {
            return 'church';
        }

        return 'person';
    }

    public function displayTitle(): string
    {
        if ($this->home_church_id !== null) {
            return 'Home Church Need — '.($this->homeChurch?->name ?? 'Home church').' — '.$this->summary;
        }

        if ($this->church_id !== null) {
            return 'Church Need — '.($this->church?->name ?? 'Church').' — '.$this->summary;
        }

        $personName = PersonDisplayName::of($this->person);

        return 'Personal Need — '.($personName !== '' ? $personName : 'Member').' — '.$this->summary;
    }
}
