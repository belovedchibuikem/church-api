<?php

namespace App\Models;

use App\Kca\KcaOrientationSessionLifecycleStatus;
use Database\Factories\KcaOrientationSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'kca_cohort_id',
    'location_id',
    'name',
    'venue_label',
    'starts_at',
    'ends_at',
    'capacity',
    'notes',
    'published_at',
])]
class KcaOrientationSession extends Model
{
    /** @use HasFactory<KcaOrientationSessionFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(KcaCohort::class, 'kca_cohort_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lifecycleStatus(): KcaOrientationSessionLifecycleStatus
    {
        return KcaOrientationSessionLifecycleStatus::forSession($this);
    }

    public function venueDisplay(): ?string
    {
        if ($this->relationLoaded('location') && $this->location?->name) {
            return $this->location->name;
        }

        $label = trim((string) ($this->venue_label ?? ''));

        return $label === '' ? null : $label;
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'capacity' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }
}
