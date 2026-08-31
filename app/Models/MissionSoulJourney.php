<?php

namespace App\Models;

use App\Mission\MissionSoulJourneyStatus;
use Database\Factories\MissionSoulJourneyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([])]
#[Hidden(['capture_idempotency_scope_hash', 'capture_payload_fingerprint'])]
class MissionSoulJourney extends Model
{
    /** @use HasFactory<MissionSoulJourneyFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function crusade(): BelongsTo
    {
        return $this->belongsTo(Crusade::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function connectedChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'connected_church_id');
    }

    public function mentorAssignment(): HasOne
    {
        return $this->hasOne(MentorAssignment::class);
    }

    public function followUpInteractions(): HasMany
    {
        return $this->hasMany(FollowUpInteraction::class);
    }

    protected function casts(): array
    {
        return [
            'status' => MissionSoulJourneyStatus::class,
            'captured_at' => 'immutable_datetime',
            'converted_at' => 'immutable_datetime',
            'mentor_assigned_at' => 'immutable_datetime',
            'last_follow_up_at' => 'immutable_datetime',
            'follow_up_completed_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }
}
