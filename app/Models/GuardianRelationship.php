<?php

namespace App\Models;

use App\Safeguarding\GuardianRelationshipStatus;
use Database\Factories\GuardianRelationshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['relationship_type'])]
class GuardianRelationship extends Model
{
    /** @use HasFactory<GuardianRelationshipFactory> */
    use HasFactory, HasUlids;

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'guardian_person_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'child_person_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(GuardianConsent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => GuardianRelationshipStatus::class,
            'verified_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
