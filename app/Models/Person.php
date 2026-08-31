<?php

namespace App\Models;

use App\Media\HasMedia;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory, HasMedia, HasUlids;

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

    public function profile(): HasOne
    {
        return $this->hasOne(PersonProfile::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(PersonConsent::class);
    }

    public function preference(): HasOne
    {
        return $this->hasOne(PersonPreference::class);
    }

    public function fileAssets(): HasMany
    {
        return $this->hasMany(FileAsset::class, 'owner_person_id');
    }

    public function childProfile(): HasOne
    {
        return $this->hasOne(ChildProfile::class);
    }

    public function guardianRelationships(): HasMany
    {
        return $this->hasMany(GuardianRelationship::class, 'guardian_person_id');
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(GuardianRelationship::class, 'child_person_id');
    }

    public function dataSubjectRequests(): HasMany
    {
        return $this->hasMany(DataSubjectRequest::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ChurchMembership::class);
    }

    public function firstTimers(): HasMany
    {
        return $this->hasMany(FirstTimer::class);
    }

    public function converts(): HasMany
    {
        return $this->hasMany(Convert::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(ChurchRoleAssignment::class);
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_person_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'archived_at' => 'immutable_datetime',
        ];
    }
}
