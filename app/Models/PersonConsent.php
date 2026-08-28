<?php

namespace App\Models;

use Database\Factories\PersonConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purpose',
    'policy_version',
    'source',
    'granted_at',
])]
class PersonConsent extends Model
{
    /** @use HasFactory<PersonConsentFactory> */
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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'granted_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }
}
