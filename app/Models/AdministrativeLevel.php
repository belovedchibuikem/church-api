<?php

namespace App\Models;

use App\Support\Organization\AdministrativeLevelCode;
use Database\Factories\AdministrativeLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['country_id', 'code', 'name', 'sort_order'])]
class AdministrativeLevel extends Model
{
    /** @use HasFactory<AdministrativeLevelFactory> */
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

    public function administrativeUnits(): HasMany
    {
        return $this->hasMany(AdministrativeUnit::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (AdministrativeLevel $level): void {
            $level->code = (new AdministrativeLevelCode((string) $level->code))->value;
        });
    }
}
