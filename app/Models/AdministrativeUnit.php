<?php

namespace App\Models;

use Database\Factories\AdministrativeUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'country_id',
    'administrative_level_id',
    'parent_id',
    'name',
    'reference_code',
])]
class AdministrativeUnit extends Model
{
    /** @use HasFactory<AdministrativeUnitFactory> */
    use HasFactory, HasUlids;

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

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function administrativeLevel(): BelongsTo
    {
        return $this->belongsTo(AdministrativeLevel::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function churches(): HasMany
    {
        return $this->hasMany(Church::class);
    }
}
