<?php

namespace App\Models;

use App\Support\Organization\GeographicCoordinates;
use App\Support\Organization\IanaTimezone;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'country_id',
    'administrative_unit_id',
    'name',
    'address_line_one',
    'address_line_two',
    'locality',
    'postal_code',
    'timezone',
    'latitude',
    'longitude',
])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
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

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class);
    }

    public function crusades(): HasMany
    {
        return $this->hasMany(Crusade::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Location $location): void {
            new IanaTimezone((string) $location->timezone);
            new GeographicCoordinates(
                $location->latitude === null ? null : (float) $location->latitude,
                $location->longitude === null ? null : (float) $location->longitude,
            );
        });
    }
}
