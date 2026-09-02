<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\KcaCohortFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kca_year_id', 'code', 'name', 'starts_on', 'ends_on', 'timezone'])]
class KcaCohort extends Model
{
    /** @use HasFactory<KcaCohortFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(KcaYear::class, 'kca_year_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(KcaEnrollment::class);
    }

    public function lifecycleStatus(): string
    {
        $today = CarbonImmutable::today();

        if ($this->starts_on !== null && $this->starts_on->gt($today)) {
            return 'upcoming';
        }

        if ($this->ends_on !== null && $this->ends_on->lt($today)) {
            return 'completed';
        }

        return 'active';
    }

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }
}
