<?php

namespace App\Models;

use App\Media\HasMedia;
use Database\Factories\MinistryEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['location_id', 'category_code', 'name', 'starts_at', 'ends_at', 'registration_opens_at', 'registration_closes_at', 'fee_amount_minor', 'fee_currency', 'capacity', 'is_important', 'published_at'])]
class MinistryEvent extends Model
{
    /** @use HasFactory<MinistryEventFactory> */
    use HasFactory, HasMedia, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    #[Scope]
    protected function publiclyUpcoming(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()->utc())
            ->where('ends_at', '>=', now()->utc());
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime',
            'registration_opens_at' => 'immutable_datetime', 'registration_closes_at' => 'immutable_datetime',
            'fee_amount_minor' => 'integer', 'capacity' => 'integer', 'is_important' => 'boolean', 'published_at' => 'immutable_datetime',
        ];
    }
}
