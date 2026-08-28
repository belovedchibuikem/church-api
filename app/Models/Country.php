<?php

namespace App\Models;

use App\Support\Organization\IsoCountryCode;
use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['iso_code', 'name'])]
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
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

    public function administrativeLevels(): HasMany
    {
        return $this->hasMany(AdministrativeLevel::class);
    }

    public function administrativeUnits(): HasMany
    {
        return $this->hasMany(AdministrativeUnit::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Country $country): void {
            $country->iso_code = (new IsoCountryCode((string) $country->iso_code))->value;
        });
    }
}
